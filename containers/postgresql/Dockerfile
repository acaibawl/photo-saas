FROM postgres:15-bookworm

ENV TZ=Asia/Tokyo \
    PGTZ=Asia/Tokyo

COPY ./containers/postgresql/postgresql.conf /etc/postgresql/postgresql.conf
COPY ./containers/postgresql/init/001-extensions.sql /docker-entrypoint-initdb.d/001-extensions.sql
COPY ./containers/postgresql/init/002-create-testing-database.sql /docker-entrypoint-initdb.d/002-create-testing-database.sql
