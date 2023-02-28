FROM docker.elastic.co/elasticsearch/elasticsearch:7.10.2
COPY --chown=elasticsearch:elasticsearch elasticsearch.yml /usr/share/elasticsearch/config/

RUN /usr/share/elasticsearch/bin/elasticsearch-plugin install analysis-icu

USER elasticsearch
