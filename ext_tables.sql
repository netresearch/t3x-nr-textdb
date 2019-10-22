#
# Table structure for table 'tx_nrtextdb_domain_model_environment'
#
CREATE TABLE tx_nrtextdb_domain_model_environment (

	name varchar(255) DEFAULT '' NOT NULL,

    UNIQUE KEY environment(name,pid,deleted),
	INDEX default_index(name,pid,deleted,hidden,starttime,endtime)

);

#
# Table structure for table 'tx_nrtextdb_domain_model_component'
#
CREATE TABLE tx_nrtextdb_domain_model_component (

	name varchar(255) DEFAULT '' NOT NULL,

    UNIQUE KEY component(name,pid,deleted),
    INDEX default_index(name,pid,deleted,hidden,starttime,endtime)

);

#
# Table structure for table 'tx_nrtextdb_domain_model_type'
#
CREATE TABLE tx_nrtextdb_domain_model_type (

	name varchar(255) DEFAULT '' NOT NULL,

    UNIQUE KEY type(name,pid,deleted),
    INDEX default_index(name,pid,deleted,hidden,starttime,endtime)
);

#
# Table structure for table 'tx_nrtextdb_domain_model_translation'
#
CREATE TABLE tx_nrtextdb_domain_model_translation (
    environment int(11) unsigned DEFAULT '0',
    component int(11) unsigned DEFAULT '0',
    type int(11) unsigned DEFAULT '0',
    placeholder varchar(255) DEFAULT '' NULL,
	value varchar(255) DEFAULT '' NOT NULL,

	UNIQUE KEY translation(sys_language_uid, pid, environment, component, type, placeholder, deleted),
	INDEX default_index(placeholder,pid,type,component,sys_language_uid,deleted),
	INDEX translated(placeholder,pid,type,component,sys_language_uid,deleted,l10n_parent),
	INDEX subquery(sys_language_uid,deleted,l10n_parent,hidden)
);
