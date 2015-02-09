

#
# Table structure for table 'tx_questionnaire_questionnaires'
#
CREATE TABLE tx_questionnaire_questionnaires (
	uid int(11) unsigned DEFAULT '0' NOT NULL auto_increment,
	pid int(11) unsigned DEFAULT '0' NOT NULL,
	tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	crdate int(11) unsigned DEFAULT '0' NOT NULL,
	cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
	sorting int(10) unsigned DEFAULT '0' NOT NULL,
	deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
	hidden tinyint(4) unsigned DEFAULT '0' NOT NULL,
	starttime int(11) unsigned DEFAULT '0' NOT NULL,
	endtime int(11) unsigned DEFAULT '0' NOT NULL,
	fe_group int(11) DEFAULT '0' NOT NULL,
	title tinytext NOT NULL,
	header text NOT NULL,
	questions text NOT NULL,
	per_page int(11) DEFAULT '0' NOT NULL,
	intro tinyint(3) unsigned DEFAULT '0' NOT NULL,
	overview tinyint(3) unsigned DEFAULT '0' NOT NULL,
	link tinytext NOT NULL,
	
	PRIMARY KEY (uid),
	KEY parent (pid)
);

#
# Table structure for table 'tx_questionnaire_answers'
#
CREATE TABLE tx_questionnaire_answers (
	uid int(11) unsigned DEFAULT '0' NOT NULL auto_increment,
	pid int(11) unsigned DEFAULT '0' NOT NULL,
	tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	crdate int(11) unsigned DEFAULT '0' NOT NULL,
	fe_user_id int(11) unsigned DEFAULT '0' NOT NULL,
	fe_user_ip varchar(15) DEFAULT '' NOT NULL,
	cookie tinytext NOT NULL,
	qid int(11) unsigned DEFAULT '0' NOT NULL,
	complete tinyint(4) unsigned DEFAULT '0' NOT NULL,
	content text NOT NULL,
	
	PRIMARY KEY (uid),
	KEY parent (pid)
);