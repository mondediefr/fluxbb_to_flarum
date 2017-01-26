# fluxbb_to_flarum

Migration script for Fluxbb forum to Flarum, partially based on work of :

- [robrotheram/phpbb_to_flarum](https://github.com/robrotheram/phpbb_to_flarum)
- [ItalianSpaceAstronauticsAssociation/smf2_to_flarum](https://github.com/ItalianSpaceAstronauticsAssociation/smf2_to_flarum)

### Description

This tool exports and migrates your Fluxbb (>= v1.5.8) forum to Flarum.

Flarum is still in beta testing, therefore only some of the typical web forum features are available, and what is now working can break anytime
(including this script, tailored for flarum v0.1.0-beta.6). At this moment fluxbb_to_flarum only supports migration of :

- Users
- User groups
- User avatars
- User signatures
- Topics
- Posts
- Categories
- Subcategories
- Topics subscriptions
- Bans
- Smileys
- Fluxbb http(s) links

### Roadmap

- Forums permissions (forum_perms -> permissions)

### Usage instructions

#### 0 - Prerequisites

If this is not already done, install docker. Read https://docs.docker.com/engine/installation/ for more information.

#### 1 - Clone and build the migrator image

```bash
git clone https://github.com/mondediefr/fluxbb_to_flarum.git && cd fluxbb_to_flarum

# and build the image migrator
./run build
```

Edit (if needed) and copy the `.env.sample` file :

```
vim .env.sample
cp .env.sample .env
```

#### 2 - Add a new flarum vhost for nginx

```bash
echo "127.0.0.1 flarum.local" >> /etc/hosts
```
Create the file flarum.conf with folders ./docker/nginx/sites-enabled/
```nginx
# File : ./docker/nginx/sites-enabled/flarum.conf

server {
  listen 8000;
  server_name flarum.local;

  location / {
    proxy_pass              http://flarum:8888;
    proxy_set_header        Host                 $host;
    proxy_set_header        X-Real-IP            $remote_addr;
    proxy_set_header        X-Forwarded-For      $proxy_add_x_forwarded_for;
    proxy_set_header        X-Remote-Port        $remote_port;
    proxy_set_header        X-Forwarded-Proto    $scheme;
    proxy_redirect          off;
  }
}
```

#### 3 - Start the containers

```bash
# make sure you use last docker image
docker pull mondedie/docker-flarum

# launch mariadb, nginx and flarum
docker-compose up -d
```

Now, you must install flarum by opening your browser and setting database parameters.
At this adress http://flarum.local

Data to set on install page
```
MySQL Host     = mariadb
MySQL Database = flarum
MySQL Username = flarum
MySQL Password = 277F9fqGEgyB
```

Init importer :

```bash
./run init

[INFO] Install migration script dependencies
[INFO] Creation of the default TextFormatter bundle
```

#### 4 - Export your fluxbb dump and init fluxbb/flarum databases

Example :

```bash
# Create a ssh tunnel to your database hosting
ssh user@domain.tld -p xxx -L 8888:localhost:3306

# Export your fluxbb database dump in `scripts/sql/fluxbb_dump.sql` file
mysqldump --host=127.0.0.1 \
  --protocol=tcp \
  --port=8888 \
  --user=root \
  --password={ROOT_PASSWORD} \
  --compress \
  --default-character-set=utf8 \
  --result-file=scripts/sql/fluxbb_dump.sql {NAME_OF_DATABASE}

# Init fluxbb database
./run fluxbb-db-init

[INFO] Init fluxbb database
[INFO] Importing the fluxbb dump
[INFO] done !
```

#### 5 - Avatars importation

Import all avatar images in `scripts/avatars` folder :

Example :

```bash
scp -P xxx -r user@domain.tld:/path/to/fluxbb/avatars/folder/* scripts/avatars
```

#### 6 - Smileys importation

Import all smileys images in `scripts/smileys` folder :

Example :

```bash
scp -P xxx -r user@domain.tld:/path/to/fluxbb/smileys/folder/* scripts/smileys
```

Add your custom fluxbb smileys in `scripts/importer/smileys.php` like this :

```php
<?php

$smileys = [
    ["smile.png",":)"],
    ["neutral.png",":|"],
    ...
];
```

#### 7 - Usernames format

Usernames must contain only letters, numbers and dashes. If an account doesn't match this format, the script
clean it and send an email to notify account's owner.

You must add your email provider settings in `.env` file in order to send notifications :

```
MAIL_FROM=noreply@domain.tld
MAIL_HOST=mail.domain.tld
MAIL_PORT=587
MAIL_ENCR=tls
MAIL_USER=noreply@domain.tld
MAIL_PASS=xxxxx
```

Edit `scripts/mail/title.txt.sample` and `scripts/mail/body.html.sample` files at your convenience, then :

```bash
cp scripts/mail/title.txt.sample scripts/mail/title.txt
cp scripts/mail/body.html.sample scripts/mail/body.html
```

If you have your own mail server, don't forget to apply rate limiting to
avoid **421** error (with gmail, outlook...etc) when sending bulk emails.

Example with postfix :

```
# /etc/postfix/main.cf

# It specifies a delay (1 second) between deliveries
default_destination_rate_delay = 1s
```

#### 8 - Migration process

```bash
./run migrate

[INFO] ------------------- STARTING MIGRATION PROCESS -------------------
[INFO] Connected successfully to the databases !
[INFO] #############################
[INFO] ### [1/8] Users migration ###
[INFO] #############################
[INFO] Migrating 2441 users...
[INFO] DONE. Results :
[INFO] > 2440 user(s) migrated successfully
[INFO] > 1 user(s) ignored (guest account + those without mail address)
[INFO] > 19 user(s) cleaned (incorrect format)
[INFO] > 119 signature(s) cleaned and migrated successfully
[INFO] > 165 avatar(s) migrated successfully
[INFO] ##################################
[INFO] ### [2/8] Categories migration ###
[INFO] ##################################
[INFO] Migrating 9 categories...
[INFO] ...
[INFO] ...
[INFO] ...
[INFO] ----------------- END OF MIGRATION (time: 3 min 41 sec) ---------------
```

Migration logs are available in `scripts/logs/migrate.log`

#### 9 - Done, congratulation ! :tada:

You can see the result of migration here : http://flarum.local

### Misc

#### TextFormatter Bundle

You can add custom TextFormatter rules in this file `scripts/createCustomBundle.php`

Then update the bundle with :

```bash
./run update-bundle

[INFO] TextFormatter bundle updated !
```

#### Docker

To remove untagged images (after some builds), run :

```bash
./run clean
```

To reset and remove all containers, run :

```bash
./run remove

# Remove mount point data
rm -rf ./docker/flarum/ \
       ./docker/mysql/
```

To restart all containers again : https://github.com/mondediefr/fluxbb_to_flarum#3---start-the-containers

### Libraries

- https://github.com/s9e/TextFormatter : Text formatting library (Thanks to @JoshyPHP for this wonderful library <3)
- https://github.com/composer/composer : PHP dependencies manager
- https://github.com/Intervention/image : PHP image handling and manipulation library
- https://github.com/illuminate/support : Illuminate support components
- https://github.com/cocur/slugify : String to slug converter
- https://github.com/PHPMailer/PHPMailer : Email sending library for PHP

### Contribute

- Fork this repository
- Create a new feature branch for a new functionality or bugfix
- Commit your changes
- Push your code and open a new pull request
- Use [issues](https://github.com/mondediefr/fluxbb_to_flarum/issues) for any questions

### Support

https://github.com/mondediefr/fluxbb_to_flarum/issues

### License

Apache License Version 2.0
