<?php
class CheckGrades extends SpecialPage
{
	function __construct()
	{
		parent::__construct('CheckGrades', 'quiz');
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
		$filterCat = $request->getText('selection');
		$filterText = $request->getText('filter');
		$gradeLevel = $request->getText('grade');

		$db = wfGetDB(DB_REPLICA);
		$where = null;
		$template = [];

		if ($filterText && $filterText != "") { // if they inputted something to filter
			$template['formText'] = htmlspecialchars($filterText);
			
			$filterCol = '';
			switch($filterCat) { // which column to search
				case 'username':
					$filterCol = 'user_name';
					break;
				case 'real-name':
					$filterCol = 'user_real_name';
					break;
				case 'page':
					$filterCol = 'page_title';
					$filterText = str_replace(" ", "_", $filterText);
					break;
				default:
					$filterCol = 'sc_data';
					break;
			}

			$where = ['LOWER(CONVERT(' . $filterCol . ' USING latin1)) ' . $db->buildLike($db->anyString(), strtolower($filterText), $db->anyString())];

			$template[$filterCat] = "selected";
		} else {
			$where = [];
		}

		if ($gradeLevel != '' && $gradeLevel != -1) {
			$where['sc_grade'] = intval($gradeLevel);
		}

		//throw new Exception($db->selectRowCount('qz_teacher', '*', ['tc_id' => $user->getId()]));
		if ($db->selectRowCount('qz_teacher', '*', ['tc_id' => $user->getId()]) > 0) { // not sure if this works
			$where['st_teacher_id'] = $user->getId();
		} else {
			$where['st_student_id'] = $user->getId();
		}

		$result = $db->select(
			['qz_student', 'qz_score', 'user', 'page'],
			['user_real_name', 'user_name', 'page_title', 'sc_percent', 'sc_data', 'sc_timestamp', 'sc_grade'],
			$where,
			__METHOD__,
			['ORDER BY' => 'sc_timestamp DESC'],
			[
				'page'=>['INNER JOIN', 'sc_page_id=page_id'],
				'user'=>['INNER JOIN', 'sc_user_id=user_id'],
				'qz_score'=>['INNER JOIN', 'sc_user_id=st_student_id']
			]
		);

		$this->constructTables($template, $result);

		$template['help'] = wfMessage('checkgrades-help')->text();

		$template['grades'] = ['a' . ($gradeLevel == '' ? -1 : $gradeLevel) => 'selected'];

		$templateParser = new TemplateParser(__DIR__ . '/templates');
		$html = $templateParser->processTemplate(
			'Grades',
			$template
			/*[
				"people"=>[
					[
					"real-name"=>"asd",
					"username"=>"agsda",
					"score"=>23,
					"date"=>"now",
					"page"=>"asome",
					"scores"=>[
						["number"=>1, "state"=>"right"],
						["number"=>2, "state"=>"wrong"],
						["number"=>3, "state"=>"wrong"],
						["number"=>4, "state"=>"right"],
						["number"=>5, "state"=>"right"],
						["number"=>6, "state"=>"wrong"],
						["number"=>7, "state"=>"wrong"]
					]
					]
				]
			]*/
		); // MAKE SURE TO SCAN EVERYTHING FOR HTML INJECTION

		$output->addHTML($html);
	}

	function constructTables(&$obj, $result) {
		$days = [7, 30]; // 7 days, 30 days, and all
		$people = [];
		$questionscores = [ // three different arrays for each question for the different time sections
			[[],[],[]],
			[[],[],[]],
			[[],[],[]],
			[[],[],[]],
			[[],[],[]],
			[[],[],[]],
			[[],[],[]]
		];
		$totalscores = [[],[],[]];

		foreach($result as $row) { // for each row
			$time = wfTimestamp(TS_UNIX, $row->sc_timestamp);

			$rawscore = $row->sc_data;
			$scores = [];

			for ($i = 6; $rawscore > 1; $i--) { // scan each bit for the score
				$correct = $rawscore & 1 == 1;
				$rawscore = $rawscore >> 1;

				array_unshift($scores, ['number' => $i + 1, 'state' => $correct ? 'right' : 'wrong']);
				$this->pushIfWithinDays($questionscores[$i][0], $correct ? 1 : 0, $time, $days[0]);
				$this->pushIfWithinDays($questionscores[$i][1], $correct ? 1 : 0, $time, $days[1]);
				array_push($questionscores[$i][2], $correct ? 1 : 0);
			}

			$this->pushIfWithinDays($totalscores[0], $row->sc_percent / 100, $time, $days[0]);
			$this->pushIfWithinDays($totalscores[1], $row->sc_percent / 100, $time, $days[1]);
			array_push($totalscores[2], $row->sc_percent / 100);

			$people[] = [
				"username" => htmlspecialchars($row->user_name),
				"real-name" => htmlspecialchars($row->user_real_name),
				"page" => htmlspecialchars(str_replace("_", " ", $row->page_title)),
				"grade" => $row->sc_grade == null ? '-' : $row->sc_grade,
				"score" => $row->sc_percent,
				"scores" => $scores,
				"date" => $time
			];

		}
		$obj['people'] = $people;

		$average = [];

		for ($i = 0; count($days) + 1 > $i; $i++) { // calculate the averages
			$avg = ['total' => $this->average($totalscores[$i]), 'questions' => []];

			for ($j = 0; count($questionscores) > $j; $j++) {
				array_push($avg['questions'], ['number' => $j + 1, 'total' => $this->average($questionscores[$j][$i])]);
			}
			array_push($average, $avg);
		}
		$obj['average'] = $average;
	}

	function pushIfWithinDays(&$array, $data, $timestamp, $days) {
		if (time() - $timestamp < ($days * 24 * 60 * 60)) {
			array_push($array, $data);
		}
	}

	function average($array) {
		return count($array) == 0 ? '--' : round(array_sum($array) / count($array) * 100, 2);
	}
}
