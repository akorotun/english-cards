FROM node:24-alpine

RUN mkdir -p /var/www/html/node_modules \
    && chown -R node:node /var/www/html

WORKDIR /var/www/html

USER node
