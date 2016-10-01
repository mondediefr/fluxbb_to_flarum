# fluxbb_to_flarum

Migration script for Fluxbb forum to Flarum, partially based on work of :

- [robrotheram/phpbb_to_flarum](https://github.com/robrotheram/phpbb_to_flarum)
- [ItalianSpaceAstronauticsAssociation/smf2_to_flarum](https://github.com/ItalianSpaceAstronauticsAssociation/smf2_to_flarum)

### Description

This tool exports and migrates your Fluxbb (v1.x) forum to Flarum.

Flarum is still in beta testing, therefore only some of the typical web forum features are available, and what is now working can break anytime
(including this script, tailored for flarum ver. 0.1.0-beta.5). At this moment fluxbb_to_flarum only supports migration of :

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

- Private Messages are not exported yet, since this feature is not yet supported by Flarum.

### Usage instructions

#### 0 - Prerequisites

If this is not already done, install docker. Read https://docs.docker.com/engine/installation/ for more information.

#### 1 - Clone and build the migrator image

```bash
git clone https://github.com/mondediefr/fluxbb_to_flarum.git && cd fluxbb_to_flarum
./run build
```

#### 2 - Add a new flarum vhost for nginx

```bash
echo "127.0.0.1 flarum.local" >> /etc/hosts
echo 'export PATH_FLARUM_MIGRATION="/path/to/folder"' >> ~/.bash_profile
```

```nginx
# File : $PATH_FLARUM_MIGRATION/docker/nginx/sites-enabled/flarum.conf

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
# Database first
docker-compose up -d mariadb
Creating mariadb

# Wait 1 minute (mariadb init and database creation), then launch nginx and flarum
docker-compose up -d
Creating flarum
Creating nginx
```

And init importer :

```bash
./run init

[INFO] Install s9e/TextFormatter lib
[INFO] Install migration script dependencies
[INFO] Generate the default TextFormatter bundle
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

$smileys = array(
    array("smile.png",":)"),
    array("neutral.png",":|"),
    ...
);
```

#### 7 - Migration process

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

#### 8 - Done, congratulation ! :tada:

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
rm -rf $PATH_FLARUM_MIGRATION/docker/flarum/ \
       $PATH_FLARUM_MIGRATION/docker/mysql/
```

To restart all containers again : https://github.com/mondediefr/fluxbb_to_flarum#3---start-the-containers

### Libraries

- https://github.com/s9e/TextFormatter : Text formatting library (Thanks to @JoshyPHP for this wonderful library <3)
- https://github.com/composer/composer : PHP dependencies manager
- https://github.com/Intervention/image : PHP image handling and manipulation library
- https://github.com/illuminate/support : Illuminate support components

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
