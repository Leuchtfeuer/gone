#
# Table structure for table 'tx_gone_path_history'
#
CREATE TABLE tx_gone_path_history (
  uid int(11) NOT NULL auto_increment,
  old varchar(255) DEFAULT '' NOT NULL,
  new varchar(255) DEFAULT '' NOT NULL,
  table varchar(255) DEFAULT '' NOT NULL,
  orig_uid int(11) DEFAULT '0' NOT NULL,
  crdate int(11) DEFAULT '0' NOT NULL,
  status varchar(255) DEFAULT '' NOT NULL,
  code int(11) DEFAULT '0' NOT NULL,
  sys_language_uid int(11) DEFAULT '0' NOT NULL,

  PRIMARY KEY (uid),
  KEY old_new (old,new)
);