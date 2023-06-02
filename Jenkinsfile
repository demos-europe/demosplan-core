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

//containerName = ""

def _dockerExecAsUser(String command, String containerName) {
    sh "echo $containerName"
    return String.format('docker exec --user $(whoami) %s /bin/zsh -c "%s"', containerName, command)
}

def _dockerExecAsRoot(String command, String containerName) {
    sh "echo $containerName"
    return String.format('docker exec %s /bin/zsh -c "%s"', containerName, command)
}

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
                    containerName = "testContainer" + env.BRANCH_NAME + env.BUILD_NUMBER
                    commandDockerRun = 'docker run -d --name ' + containerName + ' -v ${PWD}:/srv/www -v /var/cache/demosplanCI/:/srv/www/.cache/ --env CURRENT_HOST_USERNAME=$(whoami) --env CURRENT_HOST_USERID=$(id -u $(whoami)) demosdeutschland/demosplan-development:$(cat dockertag)'
                    commandExecYarn =  _dockerExecAsRoot('yarn install --prefer-offline --frozen-lockfile', containerName)
                    commandExecComposer = _dockerExecAsRoot('composer install --no-interaction', containerName)
                    sh "mkdir -p .cache"
                    sh "$commandDockerRun"
                    sh "sleep 10"
                    sh "$commandExecYarn"
                    sh "$commandExecComposer"

                }
                echo "$containerName"
             }
        }

        stage('Parallel Tests') {
            parallel {
                stage('PHPUnit: Core') {
                    steps{
                        script {
                            try {
                                commandExec = _dockerExecAsUser("APP_TEST_SHARD=core SYMFONY_DEPRECATIONS_HELPER=disabled vendor/bin/phpunit --testsuite core --log-junit .build/jenkins-build-phpunit-core.junit.xml", containerName)
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

    post {
        always{
            sh 'docker rm -f $containerName'
        }
    }
}
