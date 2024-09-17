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
    _dockerExecAsRoot(command, containerName)
    //sh "echo $containerName"
    //return String.format('docker exec --user $(whoami) %s /bin/bash -c "%s"', containerName, command)
}

def _dockerExecAsRoot(String command, String containerName) {
    sh "echo $containerName"
    return String.format('docker exec %s /bin/bash -c "%s"', containerName, command)
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
                withCredentials([usernamePassword(credentialsId: 'Docker', usernameVariable: 'USERNAME', passwordVariable: 'PASSWORD')]) {
                sh '''docker login --username $USERNAME --password $PASSWORD && docker pull demosdeutschland/demosplan-ci:latest'''
                }
                script{
                    containerName = "testContainer" + env.BRANCH_NAME + env.BUILD_NUMBER
                    commandDockerRun = 'docker run --cpus=1 -d --name ' + containerName + ' -v ${PWD}:/srv/www -v /var/cache/demosplanCI/:/srv/www/.cache/ --env CURRENT_HOST_USERNAME=$(whoami) --env CURRENT_HOST_USERID=$(id -u $(whoami)) demosdeutschland/demosplan-ci:latest'
                    commandExecYarn =  _dockerExecAsUser('YARN_CACHE_FOLDER=/srv/www/.cache/yarn yarn install --immutable --check-cache', containerName)
                    commandExecComposer = _dockerExecAsRoot('COMPOSER_CACHE_DIR=/srv/www/.cache/composer composer install --classmap-authoritative --no-interaction', containerName)
                    sh "mkdir -p .cache var"
                    sh "chmod -R 2775 var"
                    sh "chown -R dplanup:dplanup var"
                    sh "echo ${PWD}"
                    sh "$commandDockerRun"
                    //sh "sleep 10"
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
                            commandExec = _dockerExecAsUser("APP_TEST_SHARD=core SYMFONY_DEPRECATIONS_HELPER=disabled vendor/bin/phpunit --testsuite core", containerName)
                            sh "$commandExec"
                        }
                    }
                }

                stage('Run Frontend Tests') {
                    stages {
                        stage("Jest Tests") {
                            steps {
                                 script {
                                    npmTest = _dockerExecAsUser('yarn test --maxWorkers=1 --ci', containerName)
                                    sh "$npmTest"
                                }
                            }
                        }

                        stage("Webpack: Dev Build") {
                            steps {
                                script {
                                    script = _dockerExecAsUser("yarn run dev:diplanbau", containerName)
                                    sh "$script"
                                }
                            }
                        }

                        stage("Webpack: Prod Build") {
                            steps {
                                script {
                                    // The project we build the frontend for does not matter as it
                                    // is the same process for all projects.
                                    script = _dockerExecAsUser("yarn run prod:diplanbau", containerName)

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
        always {
            script {
                def containerExists = sh(script: "docker ps -a -q -f name=$containerName", returnStdout: true).trim()
                if (containerExists) {
                    sh "docker rm -f $containerName"
                }
            }
        }
    }
}
