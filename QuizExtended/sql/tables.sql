BEGIN;

CREATE TABLE IF NOT EXISTS /*_*/qz_score
(
	`sc_user_id`   int(10) UNSIGNED NOT NULL,
	`sc_page_id`   int(10) UNSIGNED NOT NULL,
	`sc_percent`   float NOT NULL,
	`sc_data`      tinyblob NOT NULL,
	`sc_timestamp` varbinary(14) NOT NULL,
	`sc_grade` 	   tinyint NULL,

	CONSTRAINT `sc_pk` PRIMARY KEY (`sc_user_id`, `sc_page_id`),
	CONSTRAINT `sc_fk_user` FOREIGN KEY (`sc_user_id`) REFERENCES /*_*/user(`user_id`)
			ON UPDATE CASCADE
			ON DELETE CASCADE,
	CONSTRAINT `sc_fk_page` FOREIGN KEY (`sc_page_id`) REFERENCES /*_*/page(`page_id`)
			ON UPDATE CASCADE
			ON DELETE CASCADE
)/*$wgDBTableOptions*/;

CREATE INDEX IF NOT EXISTS sc_score ON /*_*/qz_score (sc_user_id, sc_page_id, sc_percent);
CREATE INDEX IF NOT EXISTS sc_raw_data ON /*_*/qz_score (sc_user_id, sc_page_id, sc_percent, sc_data, sc_timestamp, sc_grade);

CREATE TABLE IF NOT EXISTS /*_*/qz_teacher
(
	`tc_id` int(10) UNSIGNED NOT NULL,

	CONSTRAINT `tc_pk` PRIMARY KEY (`tc_id`),
	CONSTRAINT `tc_fk_user` FOREIGN KEY (`tc_id`) REFERENCES /*_*/user(`user_id`)
			ON UPDATE CASCADE
			ON DELETE CASCADE
)/*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*_*/qz_student
(
	`st_student_id` int(10) UNSIGNED NOT NULL,
	`st_teacher_id` int(10) UNSIGNED NOT NULL,

	CONSTRAINT `st_pk` PRIMARY KEY (`st_student_id`, `st_teacher_id`),
	CONSTRAINT `st_fk_user` FOREIGN KEY (`st_student_id`) REFERENCES /*_*/user(`user_id`)
			ON UPDATE CASCADE
			ON DELETE CASCADE,
	CONSTRAINT `st_fk_teacher` FOREIGN KEY (`st_teacher_id`) REFERENCES /*_*/qz_teacher(`tc_id`)
			ON UPDATE CASCADE
			ON DELETE CASCADE
)/*$wgDBTableOptions*/;

COMMIT;