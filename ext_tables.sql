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
    sys_language_uid int(11)              DEFAULT '0' NOT NULL,
    l10n_parent      int(11)              DEFAULT '0' NOT NULL,
    l10n_diffsource  mediumblob,
    l10n_source      int(11)              DEFAULT '0' NOT NULL,
    deleted          smallint(5) unsigned DEFAULT '0' NOT NULL,
    hidden           smallint(5) unsigned DEFAULT '0' NOT NULL,
    sorting          int(11)              DEFAULT '0' NOT NULL,
    environment      int(10) unsigned     DEFAULT '0',
    component        int(10) unsigned     DEFAULT '0',
    type             int(10) unsigned     DEFAULT '0',
    placeholder      varchar(255)         DEFAULT ''  NOT NULL,
    value            varchar(2000)        DEFAULT ''  NOT NULL,

    PRIMARY KEY (uid),
    UNIQUE KEY translation (sys_language_uid, pid, environment, component, type, placeholder, deleted),
    KEY parent (pid, deleted, hidden),
    KEY default_index (placeholder, pid, type, component, sys_language_uid, deleted),
    KEY translated (placeholder, pid, type, component, sys_language_uid, deleted, l10n_parent),
    KEY subquery (sys_language_uid, deleted, l10n_parent, hidden),
    KEY sys_language_uid_l10n_parent (sys_language_uid, l10n_parent)
);


#
# Table structure for table 'tx_nrtextdb_import_job_status'
#
CREATE TABLE tx_nrtextdb_import_job_status
(
    uid               int(11) unsigned                   NOT NULL auto_increment,
    job_id            varchar(40)                        NOT NULL,
    status            varchar(20)          DEFAULT 'pending' NOT NULL,
    file_path         text,
    original_filename varchar(255)         DEFAULT '',
    file_size         int(11) unsigned     DEFAULT 0,
    imported          int(11) unsigned     DEFAULT 0,
    updated           int(11) unsigned     DEFAULT 0,
    errors            text,
    backend_user_id   int(11) unsigned     DEFAULT 0,
    created_at        int(11) unsigned     DEFAULT 0     NOT NULL,
    started_at        int(11) unsigned     DEFAULT 0,
    completed_at      int(11) unsigned     DEFAULT 0,

    PRIMARY KEY (uid),
    UNIQUE KEY job_id (job_id),
    KEY status (status),
    KEY created_at (created_at)
);
