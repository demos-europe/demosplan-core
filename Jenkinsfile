def containerName = "demosdeutschland/demosplan-development:4.3"

pipeline {
    agent any

    stages {
        stage('Prepare') {
            steps {
                script{
                    sh 'mkdir -p .build'
                    sh 'mkdir -p .cache'
                }
            }
        }

        stage('Setup Container') {
            steps {
                script {
                    wrap([$class: 'BuildUser']) {
                        def uname = env.BUILD_USER
                        def uid = env.BUILD_USER_ID

                        sh 'docker run -d --name ${BUILD_TAG} -v ${PWD}:/srv/www -v /var/cache/demosplanCI/:/srv/www/.cache/ --env CURRENT_HOST_USERNAME=$uname --env CURRENT_HOST_USERID=$uid $containerName'
                    }

                    sh 'sleep 10' // maybe we don't even need this?
                    sh 'yarn add file:client/ui'
                    sh 'yarn install --prefer-offline --frozen-lockfile'
                    sh 'composer install --no-interaction'
                }
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
    post {
        always{
            sh 'docker rm -f ${BUILD_TAG}'
        }
    }
}
