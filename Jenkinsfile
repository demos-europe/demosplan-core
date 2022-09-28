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


// TODO: This needs to
def containerName = "demosdeutschland/demosplan-development:4.3"

pipeline {
    options {
        buildDiscarder(logRotator(numToKeepStr: "10", daysToKeepStr: "10"))
    }

    agent {
        label 'docker && metal'
        docker {
            image containerName
            reuseNode true,
            args '-v ${PWD}:/srv/www -v /var/cache/demosplanCI/:/srv/www/.cache/ --env CURRENT_HOST_USERNAME=${BUILD_USER} --env CURRENT_HOST_USERID={BUILD_USER_ID}'
        }
    }

    stages {
        stage('Prepare') {
            steps {
                script{
                    cancelPreviousBuilds()
                    sh 'mkdir -p .build'
                    sh 'mkdir -p .cache'
                }
            }
        }

        stage('Setup Container') {
            steps {
                sh 'sleep 10' // maybe we don't even need this?
                sh 'yarn add file:client/ui'
                sh 'yarn install --prefer-offline --frozen-lockfile'
                sh 'composer install --no-interaction'
            }
        }

        stage('PHPUnit: Core') {
            steps {
                script {
                    try {
                        sh 'APP_TEST_SHARD=core SYMFONY_DEPRECATIONS_HELPER=disabled vendor/bin/phpunit --testsuite core --log-junit .build/jenkins-build-phpunit-core.junit.xml'
                    } catch (err) {
                        echo "PHPUnit Failed: ${err}"
                    }

                    junit checksName: "Core Tests", healthScaleFactor: 5.0, testResults: ".build/jenkins-build-phpunit-core.junit.xml"
                }
            }
        }
    }
}
