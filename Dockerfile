FROM php:8.3-apache

# DB(MySQL) 연동에 필요한 PHP 확장
RUN docker-php-ext-install pdo_mysql mysqli

# Cloud Run은 $PORT 환경변수(기본 8080)로 리슨해야 해서 Apache 포트를 맞춰줍니다
ENV PORT 8080
RUN sed -i "s/80/${PORT}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# 소스 전체를 웹 루트로 복사
COPY . /var/www/html/

EXPOSE 8080
