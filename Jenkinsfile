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

def projects = [
    "bimschgsh",
    "blp",
    "bobhh",
    "bobsh",
    "ewm",
    "planfestsh",
    "robobsh",
    "teilhabe",
]

def plugins = [
    "BTHGWegewerk",
    "FloodControl",
    "SegmentsManager",
    "StatementSimilarity",
    "XBauleitplanung"
]

def containerName = ""

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
                sh "mkdir -p .build"
                sh "$build"
             }
        }

        stage('Parallel Tests') {
            parallel {
                stage('PHPUnit: Projects') {
                    steps {
                        script {
                            projects.each { project ->
                                stage(project) {
                                    script {
                                        try {
                                           test = demosTester.projectTest(project)
                                           sh "$test"
                                        } catch (err) {
                                            echo "PHPUnit Failed: ${err}"
                                            currentBuild.result = 'UNSTABLE'
                                        }

                                        junit checksName: "${project} Tests", healthScaleFactor: 5.0, testResults: ".build/jenkins-build-phpunit-${project}.junit.xml"
                                    }
                                }
                            }
                        }
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
                stage('PHPUnit: Plugins') {
                    stages {
                        stage('PHPUnit: Plugins') {
                            steps{
                                script {
                                    plugins.each { plugin ->
                                        stage(plugin) {
                                            script {
                                                try {
                                                    test = demosTester.pluginTest(plugin)
                                                    sh "$test"
                                                } catch (err) {
                                                    echo "PHPUnit Failed: ${err}"
                                                }

                                                junit checksName: "Plugin `"+plugin+"` Tests", healthScaleFactor: 5.0, testResults: ".build/jenkins-build-phpunit-plugin-"+plugin+".junit.xml"
                                            }
                                        }
                                    }
                                }
                            }
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
                                    junit checksName: "Jest Tests", healthScaleFactor: 10.0, testResults: '.build/jenkins-build-jest.junit.xml'
                                }
                            }
                        }

                        stage("Webpack: Dev Build") {
                            steps {
                                script {
                                    init_project = demosTester._setParameter('blp')
                                    sh "$init_project"

                                    script = demosTester._dockerExecAsUser("bin/blp dplan:frontend:integrator && fe build blp")
                                    sh "$script"
                                }
                            }
                        }

                        stage("Webpack: Prod Build") {
                            steps {
                                script {
                                    init_project = demosTester._setParameter('blp')
                                    sh "$init_project"

                                    // The project we build the frontend for does not matter as it
                                    // is the same process for all projects.
                                    script = demosTester._dockerExecAsUser("fe build --prod blp")

                                    sh "$script"
                                }
                            }
                        }
                    }
                }

                stage('Documentation Build') {
                    steps {
                        script {
                            docTest = demosTester.docTest()

                            sh "$docTest"
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
