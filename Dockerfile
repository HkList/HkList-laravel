# 依赖构建
FROM composer AS composer

# 复制项目源码
COPY . /app

# 开始构建
RUN composer install --optimize-autoloader --no-interaction --no-progress

###########################################################################

# 父级镜像
FROM huankong233/php-nginx:latest

# 指定当前用户
USER root

# 复制项目源码
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/fpm-pool.conf ${PHP_INI_DIR}/php-fpm.d/www.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf /etc/nginx/conf.d/default.conf
COPY docker/entrypoint.sh /entrypoint.sh

# 复制构建后项目源码
COPY --from=composer /app /var/www/94list-laravel

# 赋权
RUN chmod a+x /entrypoint.sh

###########################################################################

# 环境变量
#  默认使用.env文件模式
#  0 为.env模式
#  1 为容器环境变量模式
ENV APP_INSTALL_MODE=1

# 默认工作目录
WORKDIR /var/www/html

# 开放端口
EXPOSE 8080

# 映射源码目录
VOLUME ["/var/www/html"]
# 映射备份目录
VOLUME ["/var/www/html_old"]

# 启动
ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
