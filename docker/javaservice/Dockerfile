FROM openjdk:11-jdk-bullseye

RUN apt update
RUN apt install clamdscan clamav-freshclam cm-super lmodern texlive-fonts-recommended texlive-plain-generic texlive-humanities texlive-lang-german texlive-latex-recommended texlive-luatex texlive-xetex -y
RUN freshclam

COPY demosplan.jar /opt/demosplan/demosplan.jar
COPY override.properties /opt/demosplan/override.properties

ADD ./start.sh /
RUN chmod +x /start.sh

ENTRYPOINT [ "/start.sh" ]
