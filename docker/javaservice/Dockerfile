FROM openjdk:11-jdk-bullseye

RUN apt update
RUN apt install clamdscan clamav-freshclam cm-super lmodern texlive-fonts-recommended texlive-plain-generic texlive-humanities texlive-lang-german texlive-latex-recommended texlive-luatex texlive-xetex -y
RUN freshclam

COPY demosplan.jar /opt/demosplan/demosplan.jar
COPY override.properties /opt/demosplan/override.properties

ENTRYPOINT ["java","-jar","-Xmx1024m", "-Dfile.encoding=UTF-8", "-Dlogpath=/var/logs/demosplan", "/opt/demosplan/demosplan.jar", "--spring.config.location=classpath:application.properties,/opt/demosplan/override.properties"]
