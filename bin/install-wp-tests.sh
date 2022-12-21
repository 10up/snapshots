#!/usr/bin/env bash

if [ $# -lt 3 ]; then
  echo "usage: $0 <db-name> <db-user> <db-pass> [db-host]"
  exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}

install_db() {

  # parse DB_HOST for port or socket references
  local PARTS=(${DB_HOST//\:/ })
  local DB_HOSTNAME=${PARTS[0]};
  local DB_SOCK_OR_PORT=${PARTS[1]};
  local EXTRA=""

  if ! [ -z $DB_HOSTNAME ] ; then
    if [ $(echo $DB_SOCK_OR_PORT | grep -e '^[0-9]\{1,\}$') ]; then
      EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
    elif ! [ -z $DB_SOCK_OR_PORT ] ; then
      EXTRA=" --socket=$DB_SOCK_OR_PORT"
    elif ! [ -z $DB_HOSTNAME ] ; then
      EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
    fi
  fi

  mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA || echo "Database already exists."
}

install_db

echo "Done!"
