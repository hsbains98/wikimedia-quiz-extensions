<?php

use MediaWiki\Storage\PageUpdater;
use MediaWiki\MediaWikiServices;

class PageFromDoc extends SpecialPage {
	function __construct() {
		parent::__construct('PageFromDoc', 'createpage');
	}
	
	function execute($par) {
		define('NOTMADE', '123NOTCOMPLETED123');

		$this->checkPermissions();
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();

		$output->enableClientCache(false);
		$pages = $request->getText("pages");
		$user = $this->mContext->getUser();
		$filepath = $created = false;
		
		if ($pages) { // if they have confirmed the creation
			$htmls = $this->parseDocSecond(json_decode(htmlspecialchars_decode($pages), true), false);
			$created = $this->createPages($htmls, $user);
		} else {
			$filepath = $request->getFileTempname("file");
			
			$pages = null;
			$grade = $request->getText("grade");

			if ($filepath) { // if they have submitted a file
				$pages = $this->parseDocFirst($filepath, $grade ? $grade : false);
			}
		}
		
		$templateParser = new TemplateParser(__DIR__ . '/templates');
		
		$html = $templateParser->processTemplate(
			'PageFromDoc',
			[
				'confirm' => !!$filepath,
				'pages' => htmlspecialchars(json_encode($pages))
			]
		);
		
		if ($filepath) { // make the preview for the quizzes
			$html2 = implode(str_repeat('-', 75) . "<br>", $this->parseDocSecond($pages, true));
			$this->addWikiTextAll($output, $html2);
		}

		$output->addHTML($html);

		if ($created) { // if the quizzes have been created
			$html = "<b>Warning</b>: Open pages in a new tab or else this list will be lost<br>";
			foreach ($created as $t) { // differentiate between already created pages and not
				if (strpos($t, NOTMADE)) {
					$html .= '[[' . str_replace(NOTMADE, '',  $t) . "]] was '''not''' created, already existed <br>";
				}else {
					$html .= '[[' . $t . "]] was created <br>";
				}
			}
			$this->addWikiTextAll($output, $html);
		}

	}

	function addWikiTextAll($output, $content) {
		if ( method_exists( $output, 'addWikiTextAsInterface' ) ) { // new
			$output->addWikiTextAsInterface( $content );
		} else { // old
			$output->addWikiText( $content );
		}
	}

	// first part of parsing the doc, split in two to be able to save inbetween
	function parseDocFirst($filepath, $grade = null) {
		$striped_content = '';
		$content = '';

		$zip = zip_open($filepath);

		if (!(!$zip || is_numeric($zip))) { // reading the doc file which is just a zip in disguise 

			while ($zip_entry = zip_read($zip)) {

				if (zip_entry_open($zip, $zip_entry) == FALSE) continue;

				if (zip_entry_name($zip_entry) != "word/document.xml") continue;

				$content .= zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));

				zip_entry_close($zip_entry);
			}

			zip_close($zip);

			$content = str_replace('</w:r></w:p></w:tc><w:tc>', " ", $content);
			$content = str_replace('</w:r></w:p>', "\r\n", $content);
			$striped_content = strip_tags($content);
		}

		$rawsplit = explode("\n", $striped_content);
		$pages = [
			'titles' => [],
			'articles' => [],
			'questions' => [],
			'answers' => [],
			'explanations' => []
		];
		$state = 'title';
		$j = $qnum = $ansnum = $keynum = 0;

		$answernum = 4;

		$split = [];

		for ($i = 0; count($rawsplit) > $i; $i++) {
			if (preg_match('/\w/',$rawsplit[$i])) { // get rid of any empty lines
				$split[] = $rawsplit[$i];
			}
		}
		for ($i = 0; count($split) > $i; $i++) { // state machine to parse the doc
			$line = $split[$i];
			$line = preg_replace('/^[1-9.)(:-]*\s*/', '', $line); //get rid of any "2. " or "2) " 
			switch($state) {
				case 'title':
					$pages['titles'][$j] = $line;
					$state = 'article';
				break;
				case 'article':
					$pages['articles'][$j] = $line;
					$state = 'question';
				break;
				case 'question':
					if (preg_match('/answer[^\w]*key/i', $line)) {
						$state = 'key';
					break;
					}
					$pages['questions'][$j][$qnum] = [$line];
					$state = 'answer';
				break;
				case 'answer':
					$pages['questions'][$j][$qnum][1][] = $line;
					$ansnum++;
					if ($ansnum >= $answernum) {
						$state = 'question';
						$ansnum = 0;
						$qnum++;
					}
				break;
				case 'key':
					preg_match('/^\s*([a-z])\s*(.*)$/i', $line, $preg);
					print $line;
					var_dump($preg);
					$pages['answers'][$j][$keynum] = $preg[1];
					$pages['explanations'][$j][$keynum] = $preg[2];
					$keynum++;
					if ($keynum >= $qnum) {
						$state = 'title';
						$ansnum = 0;
						$qnum = 0;
						$keynum = 0;
						$j++;
					}
				break;
			}
		}

		$pages['grade'] = $grade;

		return $pages;
	}

	function parseDocSecond($pages, $preview) {
		$html = [];
		for ($i = 0; count($pages['titles']) > $i; $i++) {
			$questions = [];

			for ($j = 0; count($pages['questions'][$i]) > $j; $j++) { // this formats it into quiz extension format
				$questions[$j] = ['question' => $pages['questions'][$i][$j][0], 'ans' => []];
				
				for ($k = 0; count($pages['questions'][$i][$j][1]) > $k; $k++) {
					$text = ($k == ord(strtoupper($pages['answers'][$i][$j])) - 65 ? '+ ' : '- ') . $pages['questions'][$i][$j][1][$k];

					if ($pages['explanations'][$i][$j] != '') {
						$text .= "\n|| " . strtoupper($pages['answers'][$i][$j]) . " is the correct answer because: " . $pages['explanations'][$i][$j];
					}
					$questions[$j]['ans'][] = $text;
				}
			}
			$templateParser = new TemplateParser(__DIR__ . '/templates');
			
							// quiz extension does not work with carriage returns
			$html[] = preg_replace("/[\r]/", "", $templateParser->processTemplate(
				$preview ? 'QuizDisplay' : 'Quiz',
				[
					'title' => $pages['titles'][$i],
					'article' => $pages['articles'][$i],
					'questions' => $questions,
					'grade' => $pages['grade']
				]
			));
		}
		//var_dump($html);
		return $html;
	}

	function createPages($pages, $user) {
		$titles = [];
		foreach ($pages as $key => $s) { // get the titles from the parsed doc
			$ex = explode("\n", $s);
			$titles[] = trim(array_shift($ex));
			$pages[$key] = implode("\n", $ex);
		}
		$output = [];
		for ($i = 0; $i < count($pages); $i++) {
			$title = Title::newFromText($titles[$i], NS_QUIZ); // NS_QUIZ is defined in extension.json

			if (false) {
				for ($j = 1; $title->exists(); $j++) { // keep incrementing until you get to a page that has not been created yet, mostly for testing although can be a feature
					$title = Title::newFromText($titles[$i] . '(' . $j . ')', NS_QUIZ);
				}
			}
			else {
				if ($title->exists()) { // if the page has already been created
					$titles[$i] = $title->getRootTitle() . NOTMADE;
					continue;
				}
			}

			$titles[$i] = $title->getRootTitle();
			$page = new WikiPage($title);

			if (method_exists( $page, 'newPageUpdater' )) { //newer method to make an edit
				$updater = $page->newPageUpdater($user);
				$updater->setContent('main', ContentHandler::makeContent($pages[$i], $title));
				$updater->saveRevision(CommentStoreComment::newUnsavedComment('Creation from PageFromDoc extension'), EDIT_NEW);
			} else { // older method
				$pageContent = ContentHandler::makeContent( $pages[$i], $title );
				$page->doEditContent( $pageContent, 'Creation from PageFromDoc extension', EDIT_NEW, false, $user );
			}
			WikiPage::onArticleCreate($title);
		}
		return $titles;
	}
}
