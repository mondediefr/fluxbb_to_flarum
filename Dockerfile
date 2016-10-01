FROM alpine:edge
MAINTAINER Hardware <contact@meshup.net>

RUN echo "@commuedge http://nl.alpinelinux.org/alpine/edge/community" >> /etc/apk/repositories \
 && apk -U add \
    git \
    pcre \
    mariadb-client \
    php7@commuedge \
    php7-pdo_mysql@commuedge \
    php7-iconv@commuedge \
    php7-mbstring@commuedge \
    php7-dom@commuedge \
    php7-curl@commuedge \
    php7-intl@commuedge \
    php7-json@commuedge \
    php7-xsl@commuedge \
    php7-zlib@commuedge \
    php7-gd@commuedge

VOLUME /scripts
ENTRYPOINT ["/scripts/startup.sh"]
