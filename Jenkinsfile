import net.sf.json.JSONArray;
import net.sf.json.JSONObject;
import hudson.tasks.test.AbstractTestResultAction;
import hudson.model.Actionable;

@NonCPS
def cancelPreviousBuilds() {
    def jobName = env.JOB_NAME
    def buildNumber = env.BUILD_NUMBER.toInteger()
    /* Get job name */
    def currentJob = Jenkins.instance.getItemByFullName(jobName)

    /* Iterating over the builds for specific job */
    for (def build : currentJob.builds) {
        /* If there is a build that is currently running and it's not current build */
        if (build.isBuilding() && build.number.toInteger() != buildNumber) {
            /* Than stopping it */
            build.doStop()
        }
    }
}


def _dockerExecAsUser(String command) {
    command = String.format('docker exec --user $(whoami) %s /bin/zsh -c "%s"', containerName, command)
    return _exec(command)
}

def containerName = ""

pipeline {
    agent {label 'docker && metal'}
    options {
        timeout(time: 1, unit: 'HOURS')
        buildDiscarder(logRotator(numToKeepStr: "10", daysToKeepStr: "10"))
    }
    stages {
        stage('Check for previous builds') {
            steps {
                script{
                    cancelPreviousBuilds()
                }
            }
        }
        stage('Preparations') {
            steps {
                sh '''grep -A3 'development:' /etc/dp/containers.yml | tail -n1 | awk '{ print $2}' | sed 's/"//g' > dockertag'''
                withCredentials([usernamePassword(credentialsId: 'Docker', usernameVariable: 'USERNAME', passwordVariable: 'PASSWORD')]) {
                sh '''docker login --username $USERNAME --password $PASSWORD && docker pull demosdeutschland/demosplan-development:$(cat dockertag) '''
                }
                script{
                    containerName = testContainer + env.BRANCH_NAME + env.BUILD_NUMBER
                    build = String dockerRunCommand = [
                            'docker run -d --name ' + containerName,

                            // link the code directory
                            '-v ${PWD}:/srv/www',
                            // all caches mounted
                            '-v /var/cache/demosplanCI/:/srv/www/.cache/',

                            // container username is host username
                            '--env CURRENT_HOST_USERNAME=$(whoami)',
                            '--env CURRENT_HOST_USERID=$(id -u $(whoami))',

                            // todo: probably not optimal to get tagname from file
                            'demosdeutschland/demosplan-development:$(cat dockertag)'
                        ].join(' ')

                        return [
                            'mkdir -p .cache',
                            dockerRunCommand,
                            'sleep 10',
                            _dockerExecAsUser('yarn add file:client/ui'),
                            _dockerExecAsUser('yarn install --prefer-offline --frozen-lockfile'),
                            _dockerExecAsUser('composer install --no-interaction')
                        ].join(' && ')
                    env.CONTAINER_NAME = containerName
                }
                echo "$CONTAINER_NAME"
                sh "$build"
             }
        }

        stage('Parallel Tests') {
            parallel {
                stage('PHPUnit: Core') {
                    steps{
                        script {
                            try {
                                commandExec = _dockerExecAsUser(APP_TEST_SHARD=core SYMFONY_DEPRECATIONS_HELPER=disabled vendor/bin/phpunit --testsuite core --log-junit .build/jenkins-build-phpunit-core.junit.xml)
                                sh "commandExec"
                            } catch (err) {
                                echo "PHPUnit Failed: ${err}"
                            }

                            junit checksName: "Core Tests", healthScaleFactor: 5.0, testResults: "var/build/phpunit-core.junit.xml"
                        }
                    }
                }

                stage('Run Frontend Tests') {
                    stages {
                        stage("Jest Tests") {
                            steps {
                                 script {
                                    npmTest = _dockerExecAsUser('yarn test --ci')
                                    sh "$npmTest"
                                    junit checksName: "Jest Tests", healthScaleFactor: 10.0, testResults: 'var/build/jest.junit.xml'
                                }
                            }
                        }

                        stage("Webpack: Dev Build") {
                            steps {
                                script {
                                    script = _dockerExecAsUser("yarn run dev:diplanbau")
                                    sh "$script"
                                }
                            }
                        }

                        stage("Webpack: Prod Build") {
                            steps {
                                script {
                                    // The project we build the frontend for does not matter as it
                                    // is the same process for all projects.
                                    script = _dockerExecAsUser("yarn run prod:diplanbau")

                                    sh "$script"
                                }
                            }
                        }
                    }
                }
            }
        }
    }

//    post {
//        always{
//            sh 'docker rm -f $CONTAINER_NAME'
//        }
//    }
}
