#use the latest stable PHP + Apache
FROM php:8.3-Apache

#Enable Apache rewrite
RUN a2enmod rewrite

#Install required PHP etension

RUN docker-php-ext-install \
                mysqli \
                pdo \
                pdo_mysql \


COPY ./var/www/html/

RUN chown -R www-data:www-data /var/www/html \
            && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]