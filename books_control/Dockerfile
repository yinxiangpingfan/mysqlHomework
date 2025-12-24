FROM php:8.2-apache

# 安装PHP扩展
RUN docker-php-ext-install pdo pdo_mysql mysqli

# 启用Apache mod_rewrite
RUN a2enmod rewrite

# 设置工作目录
WORKDIR /var/www/html

# 复制项目文件
COPY . /var/www/html/

# 设置权限
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
