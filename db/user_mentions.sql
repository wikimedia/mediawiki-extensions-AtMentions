CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/user_mentions (
	`um_user` INT unsigned NOT NULL,
	`um_author` INT unsigned NOT NULL,
	`um_page` INT unsigned NOT NULL,
    `um_rev` INT unsigned NOT NULL,
	PRIMARY KEY (um_user,um_rev)
	) /*$wgDBTableOptions*/;
