#!/bin/sh

action=$1

SQL_PATH="/scripts/sql"
AVATAR_PATH="/flarum/app/assets/avatars"
SMILEYS_PATH="/flarum/app/assets/images/smileys"

# Databases and mail authentification params
ENV_PARAMS=" \
  dbHost=${DB_HOST} \
  &dbFluxbbUser=${DB_FLUXBB_USER} \
  &dbFluxbbName=${DB_FLUXBB_NAME} \
  &dbFluxbbPass=${DB_FLUXBB_PASS} \
  &dbFlarumUser=${DB_FLARUM_USER} \
  &dbFlarumName=${DB_FLARUM_NAME} \
  &dbFlarumPass=${DB_FLARUM_PASS} \
  &dbFluxbbPrefix=${DB_FLUXBB_PREFIX} \
  &mailFrom=${MAIL_FROM} \
  &mailHost=${MAIL_HOST} \
  &mailPort=${MAIL_PORT} \
  &mailEncr=${MAIL_ENCR} \
  &mailUser=${MAIL_USER} \
  &mailPass=${MAIL_PASS}
"

if [ -f "${SQL_PATH}/fluxbb_init.sql" ]; then
  rm -f "${SQL_PATH}/fluxbb_init.sql"
fi

cat > "${SQL_PATH}/fluxbb_init.sql" <<EOF
DROP USER IF EXISTS ${DB_FLUXBB_USER};
DROP DATABASE IF EXISTS ${DB_FLUXBB_NAME};
CREATE database ${DB_FLUXBB_NAME};
CREATE USER '${DB_FLUXBB_USER}'@'%' IDENTIFIED BY '${DB_FLUXBB_PASS}';
GRANT USAGE ON *.* TO '${DB_FLUXBB_USER}'@'%';
GRANT ALL PRIVILEGES ON ${DB_FLUXBB_NAME}.* TO '${DB_FLUXBB_USER}'@'%';
EOF

if [ -f "${SQL_PATH}/flarum_init.sql" ]; then
  rm -f "${SQL_PATH}/flarum_init.sql"
fi

cat > "${SQL_PATH}/flarum_init.sql" <<EOF
DROP USER IF EXISTS ${DB_FLARUM_USER};
DROP DATABASE IF EXISTS ${DB_FLARUM_NAME};
CREATE database ${DB_FLARUM_NAME};
CREATE USER '${DB_FLARUM_USER}'@'%' IDENTIFIED BY '${DB_FLARUM_PASS}';
GRANT USAGE ON *.* TO '${DB_FLARUM_USER}'@'%';
GRANT ALL PRIVILEGES ON ${DB_FLARUM_NAME}.* TO '${DB_FLARUM_USER}'@'%';
EOF

if [ ! -f "/scripts/composer.lock" ]; then
  echo "[INFO] Install migration script dependencies"
  composer install --quiet --working-dir=/scripts
fi

if [ -d "${AVATAR_PATH}" ]; then
  rm -rf "${AVATAR_PATH}/*"
else
  mkdir -p $AVATAR_PATH
fi

if [ -d "${SMILEYS_PATH}" ]; then
  rm -rf "${SMILEYS_PATH}/*"
else
  mkdir -p $SMILEYS_PATH
fi

cp -r /scripts/smileys/* $SMILEYS_PATH > /dev/null 2>&1

if [ ! -f "/scripts/TextCustomBundle/TextFormatter.php" ]; then
  echo "[INFO] Creation of the default TextFormatter bundle"
  php7 -f /scripts/createCustomBundle.php
fi

case "$action" in
  "migrate")
    php7 -f /scripts/migrate.php -- "$ENV_PARAMS"
    ;;
  "update-bundle")
    rm -f /scripts/TextCustomBundle/Renderer_*.php
    php7 -f /scripts/createCustomBundle.php
    echo "[INFO] TextFormatter bundle updated !"
    ;;
  "fluxbb-db-init")
    echo "[INFO] Init fluxbb database"
    mysql -h"${DB_HOST}" -u"root" -p"${DB_ROOT_PASS}" < "${SQL_PATH}/fluxbb_init.sql"
    if [ -f "${SQL_PATH}/fluxbb_dump.sql" ]; then
      echo "[INFO] Importing the fluxbb dump"
      mysql -h"${DB_HOST}" -u"${DB_FLUXBB_USER}" -p"${DB_FLUXBB_PASS}" "${DB_FLUXBB_NAME}" < "${SQL_PATH}/fluxbb_dump.sql"
    fi
    echo "[INFO] done !"
    ;;
  "flarum-db-init")
    echo "[INFO] Init flarum database"
    mysql -h"${DB_HOST}" -u"root" -p"${DB_ROOT_PASS}" < "${SQL_PATH}/flarum_init.sql"
    echo "[INFO] done !"
    ;;
esac

exit 0
