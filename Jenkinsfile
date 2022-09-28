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
    agent {label 'docker && metal'}
    options {
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
            // TODO: move to proper jenkins docker support
                withCredentials([usernamePassword(credentialsId: 'Docker', usernameVariable: 'USERNAME', passwordVariable: 'PASSWORD')]) {
                sh '''docker login --username $USERNAME --password $PASSWORD && docker pull demosdeutschland/{$containerName}'''
                }
                script{
                    demosTester.construct("testContainer", env.BRANCH_NAME + env.BUILD_NUMBER)
                    build = demosTester.buildContainer()
                    env.CONTAINER_NAME = demosTester.containerName
                }
                echo "$CONTAINER_NAME"
                sh "mkdir -p .build"
                sh "$build"
            }
        }

        stage('PHPUnit: Core') {
            steps{
                script {
                    try {
                        test = demosTester.coreTest()
                        sh "$test"
                    } catch (err) {
                        echo "PHPUnit Failed: ${err}"
                    }

                    junit checksName: "Core Tests", healthScaleFactor: 5.0, testResults: ".build/jenkins-build-phpunit-core.junit.xml"
                }
            }
        }
    }
    post {
        always{
            sh 'docker rm -f $CONTAINER_NAME'
        }
    }
}
