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

@Library('DemosTester')
def demosTester = new main.testing.DemosTester()
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
                    demosTester.construct("testContainer", env.BRANCH_NAME + env.BUILD_NUMBER)
                    build = demosTester.buildContainer()
                    env.CONTAINER_NAME = demosTester.containerName
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
                               test = demosTester.coreTest()
                               sh "$test"
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
                                    npmTest = demosTester.npmTest()

                                    sh "$npmTest"
                                    junit checksName: "Jest Tests", healthScaleFactor: 10.0, testResults: 'var/build/jest.junit.xml'
                                }
                            }
                        }

                        stage("Webpack: Dev Build") {
                            steps {
                                script {
                                    init_project = demosTester._setParameter('diplanbau')
                                    sh "$init_project"

                                    script = demosTester._dockerExecAsUser("yarn run dev:diplanbau")
                                    sh "$script"
                                }
                            }
                        }

                        stage("Webpack: Prod Build") {
                            steps {
                                script {
                                    init_project = demosTester._setParameter('diplanbau')
                                    sh "$init_project"

                                    // The project we build the frontend for does not matter as it
                                    // is the same process for all projects.
                                    script = demosTester._dockerExecAsUser("yarn run prod:diplanbau")

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
            sh 'docker rm -f $CONTAINER_NAME'
        }
    }
}
