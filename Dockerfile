# Use uma imagem oficial do PHP com suporte a extensões e Apache
FROM php:7.2-apache

# Instale dependências do sistema e extensões PHP necessárias
RUN apt-get update && apt-get install -y \
    libpq-dev libzip-dev unzip \
    libjpeg-dev libpng-dev \
    libz-dev libmemcached-dev memcached libmemcached-tools \ 
    && docker-php-ext-install pdo pdo_mysql zip gd \
    && pecl install memcached \
    && docker-php-ext-enable memcached

# Instale Composer
COPY --from=composer:1.10.27 /usr/bin/composer /usr/bin/composer

# Configure o diretório de trabalho
WORKDIR /var/www/html

# Copie o arquivo 000-default.conf para o Apache
COPY 000-default.conf /etc/apache2/sites-enabled/000-default.conf

# Configure permissões para a pasta de armazenamento
# RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Habilite o módulo Apache Rewrite
RUN a2enmod rewrite

# Configure a porta
EXPOSE 80

# Comando para iniciar o Apache
CMD ["apache2-foreground"]
