<?php
class SpecialTeacher extends SpecialPage
{
	function __construct()
	{
		parent::__construct('SelectTeacher', 'quiz');
		$this->mIncludable = true;
	}

	function execute($par)
	{
		$this->checkPermissions();
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();
		
		$output->enableClientCache(false);

		$param = $request->getText('username');
		$user = $this->mContext->getUser();

		$db = wfGetDB(DB_REPLICA);
		$error = false;
		$teacher = false;

		if ($param && $param != "") { // if something was inputted
			$teacherID = $db->selectRow( //get a user id that exists in both the qz_teacher and user table from username
				array('user', 'qz_teacher'),
				array('user_id', 'user_real_name'),
				array('user_name' => $param),
				__METHOD__,
				[],
				['qz_teacher' => ['INNER JOIN', 'user_id=tc_id']]
			);
			if (!$teacherID) { // teacher not found
				$error = wfMessage('selectteacher-name-error', htmlspecialchars($param))->text();
			} else {
				try { // remove any previous teacher and add the new one
					$db = wfGetDB(DB_MASTER);
					$db->delete( 
						'qz_student',
						array('st_student_id' => $user->getId())
					);
					$db->insert(
						'qz_student',
						array(
							'st_student_id' => $user->getId(),
							'st_teacher_id' => $teacherID->user_id
						)
					);
					$teacher = $teacherID->user_real_name == "" ? htmlspecialchars($param) : $teacherID->user_real_name;
				} catch (Exception $e) {
					$error = wfMessage('selectteacher-default-error')->text();
				}
			}
		} else { // nothing inputted
			$teacherID = $db->selectRow( // get the teacher of the current student
				array('user', 'qz_student'),
				array('user_real_name', 'user_name', 'user_id'),
				array('st_student_id' => $user->getId()),
				__METHOD__,
				[],
				['qz_student' => ['INNER JOIN', 'user_id=st_teacher_id']]
			);
			
			if ($teacherID) {
				$teacher = $teacherID->user_real_name == "" || $teacherID->user_real_name == null ? $teacherID->user_name : $teacherID->user_real_name;
			} else {
				$error = wfMessage('selectteacher-no-teacher')->text();
			}
		}

		$templateParser = new TemplateParser(__DIR__ . '/templates');
		$html = $templateParser->processTemplate(
			'Teacher',
			[
				'desc' => wfMessage('selectteacher-top')->text(),
				'teacher' => $teacher,
				'error' => $error
			]
		);

		$output->addHTML($html);
	}
}
