#
# Table structure for table 'tx_nrtextdb_domain_model_environment'
#
CREATE TABLE tx_nrtextdb_domain_model_environment
(
    uid       int(11)                          NOT NULL auto_increment,
    pid       int(11)              DEFAULT '0' NOT NULL,
    tstamp    int(11)              DEFAULT '0' NOT NULL,
    crdate    int(11)              DEFAULT '0' NOT NULL,
    deleted   smallint(5) unsigned DEFAULT '0' NOT NULL,
    hidden    smallint(5) unsigned DEFAULT '0' NOT NULL,
    starttime int(11)              DEFAULT '0' NOT NULL,
    endtime   int(11)              DEFAULT '0' NOT NULL,
    name      varchar(255)         DEFAULT ''  NOT NULL,

    PRIMARY KEY (uid),
    UNIQUE KEY environment (name, pid, deleted),
    KEY parent (pid, deleted, hidden),
    KEY default_index (name, pid, deleted, hidden, starttime, endtime)
);


#
# Table structure for table 'tx_nrtextdb_domain_model_component'
#
CREATE TABLE tx_nrtextdb_domain_model_component
(
    uid       int(11)                          NOT NULL auto_increment,
    pid       int(11)              DEFAULT '0' NOT NULL,
    tstamp    int(11)              DEFAULT '0' NOT NULL,
    crdate    int(11)              DEFAULT '0' NOT NULL,
    deleted   smallint(5) unsigned DEFAULT '0' NOT NULL,
    hidden    smallint(5) unsigned DEFAULT '0' NOT NULL,
    starttime int(11)              DEFAULT '0' NOT NULL,
    endtime   int(11)              DEFAULT '0' NOT NULL,
    name      varchar(255)         DEFAULT ''  NOT NULL,

    PRIMARY KEY (uid),
    UNIQUE KEY component (name, pid, deleted),
    KEY parent (pid, deleted, hidden),
    KEY default_index (name, pid, deleted, hidden, starttime, endtime)
);


#
# Table structure for table 'tx_nrtextdb_domain_model_type'
#
CREATE TABLE tx_nrtextdb_domain_model_type
(
    uid       int(11)                          NOT NULL auto_increment,
    pid       int(11)              DEFAULT '0' NOT NULL,
    tstamp    int(11)              DEFAULT '0' NOT NULL,
    crdate    int(11)              DEFAULT '0' NOT NULL,
    deleted   smallint(5) unsigned DEFAULT '0' NOT NULL,
    hidden    smallint(5) unsigned DEFAULT '0' NOT NULL,
    starttime int(11)              DEFAULT '0' NOT NULL,
    endtime   int(11)              DEFAULT '0' NOT NULL,
    sorting   int(11)              DEFAULT '0' NOT NULL,
    name      varchar(255)         DEFAULT ''  NOT NULL,

    PRIMARY KEY (uid),
    UNIQUE KEY type (name, pid, deleted),
    KEY parent (pid, deleted, hidden),
    KEY default_index (name, pid, deleted, hidden, starttime, endtime)
);


#
# Table structure for table 'tx_nrtextdb_domain_model_translation'
#
CREATE TABLE tx_nrtextdb_domain_model_translation
(
    uid              int(11)                          NOT NULL auto_increment,
    pid              int(11)              DEFAULT '0' NOT NULL,
    tstamp           int(11)              DEFAULT '0' NOT NULL,
    crdate           int(11)              DEFAULT '0' NOT NULL,
    deleted          smallint(5) unsigned DEFAULT '0' NOT NULL,
    hidden           smallint(5) unsigned DEFAULT '0' NOT NULL,
    sorting          int(11)              DEFAULT '0' NOT NULL,
    sys_language_uid int(11)              DEFAULT '0' NOT NULL,
    l10n_parent      int(11)              DEFAULT '0' NOT NULL,
    l10n_diffsource  mediumblob,
    l10n_state       text,
    environment      int(10) unsigned     DEFAULT '0',
    component        int(10) unsigned     DEFAULT '0',
    type             int(10) unsigned     DEFAULT '0',
    placeholder      varchar(255)         DEFAULT '' NOT NULL,
    value            varchar(2000)        DEFAULT '' NOT NULL,

    PRIMARY KEY (uid),
    UNIQUE KEY translation (sys_language_uid, pid, environment, component, type, placeholder, deleted),
    KEY parent (pid, deleted, hidden),
    KEY default_index (placeholder, pid, type, component, sys_language_uid, deleted),
    KEY translated (placeholder, pid, type, component, sys_language_uid, deleted, l10n_parent),
    KEY subquery (sys_language_uid, deleted, l10n_parent, hidden)
);
