<?php
class StudentOverview extends SpecialPage
{
	function __construct()
	{
		parent::__construct('StudentOverview', 'quiz');
		$this->mIncludable = true;
	}

	function execute($par)
	{
		$this->checkPermissions();
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();
		$this->getOutput()->addModules( 'ext.grades' );
		$output->enableClientCache(false);

		$user = $this->mContext->getUser();

		$db = wfGetDB(DB_REPLICA);
		
		$result = $db->select( // get info from all students of this teacher
			['qz_student', 'qz_score', 'user'],
			['st_student_id', 'user_real_name', 'user_name', 'sc_percent', 'sc_timestamp'],
			[$db->makeList(['st_teacher_id' => $user->getId(), 'st_student_id' => $user->getId()], LIST_OR)],
			__METHOD__,
			[
				'ORDER BY' => 'user_real_name DESC, user_name DESC'
			],
			[
				'qz_student'=>['FULL JOIN', 'sc_user_id=st_student_id'],
				'user'=>['INNER JOIN', 'sc_user_id=user_id'],
			]
		);

		$template = ['people' => []];
		
		$days = [7, 30];
		foreach($result as $row) { // seperate averages based on time
			if(!array_key_exists($row->st_student_id, $template['people'])) {
				$template['people'][$row->st_student_id] = [
					'username' => $row->user_name,
					'real-name' => $row->user_real_name,
					'week' => [],
					'month' => [],
					'total' => []
				];
			}

			$time = wfTimestamp(TS_UNIX, $row->sc_timestamp);
			
			$this->pushIfWithinDays($template['people'][$row->st_student_id]['week'], $row->sc_percent, $time, $days[0]);
			$this->pushIfWithinDays($template['people'][$row->st_student_id]['month'], $row->sc_percent, $time, $days[1]);
			array_push($template['people'][$row->st_student_id]['total'], $row->sc_percent);
		}

		$result2 = $db->select( // don't remember why I have a second sql statement
			['qz_student', 'user'],
			['st_student_id', 'user_real_name', 'user_name'],
			['st_teacher_id' => $user->getId()],
			__METHOD__,
			[],
			[
				'user'=>['INNER JOIN', 'st_student_id=user_id']
			]
		);

		foreach($result2 as $row) {
			if(!array_key_exists($row->st_student_id, $template['people'])) {
				$template['people'][$row->st_student_id] = [
					'username' => $row->user_name,
					'real-name' => $row->user_real_name,
					'week' => [],
					'month' => [],
					'total' => []
				];
			}
		}

		$tmp = [];
		while(count($template['people']) != 0) {
			$person = array_pop($template['people']);
			$person['week'] = $this->average($person['week']);
			$person['month'] = $this->average($person['month']);
			$person['total'] = $this->average($person['total']);
			array_unshift($tmp, $person);
		}
		$template['people'] = $tmp;

		usort($template['people'], ['StudentOverview', 'mysort']);
		

		$templateParser = new TemplateParser(__DIR__ . '/templates');
		$html = $templateParser->processTemplate(
			'Overview',
			$template
				
				#'desc' => wfMessage('selectteacher-top')->text(),
				#'teacher' => $teacher,
				#'error' => $error
			
		);

		$output->addHTML($html);
	}

	function pushIfWithinDays(&$array, $data, $timestamp, $days) {
		if (time() - $timestamp < ($days * 24 * 60 * 60)) {
			array_push($array, $data);
		}
	}

	function average($array) {
		return count($array) == 0 ? '--' : round(array_sum($array) / count($array), 2);
	}

	static function mysort($a, $b) {
		$r = strcasecmp($a['real-name'], $b['real-name']);
		return $r == 0 ? strcasecmp($a['username'], $b['username']) : $r;
	}
}
