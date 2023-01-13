FROM rabbitmq:3.11-management

RUN apt update && apt install gettext-base -y

ADD ./start.sh /
RUN chmod +x /start.sh

ENTRYPOINT [ "/start.sh" ]
