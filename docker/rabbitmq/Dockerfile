FROM rabbitmq:3.11-management

ADD ./start.sh /
RUN chmod +x /start.sh

ENTRYPOINT [ "/start.sh" ]
