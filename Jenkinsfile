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
                        echo $(env.BUILD_USER)
                        echo $(env.BUILD_USER_ID)

                        sh """
                            docker run -d --name ${BUILD_TAG} \
                                -v ${PWD}:/srv/www -v /var/cache/demosplanCI/:/srv/www/.cache/ \
                                --env CURRENT_HOST_USERNAME=${env.BUILD_USER} \
                                --env CURRENT_HOST_USERID=${env.BUILD_USER_ID} \
                                -w /srv/www \
                                $containerName
                        """
                    }

                    sh 'sleep 10' // maybe we don't even need this?
                    sh 'docker exec ${BUILD_TAG} yarn add file:client/ui'
                    sh 'docker exec ${BUILD_TAG} yarn install --prefer-offline --frozen-lockfile'
                    sh 'docker exec ${BUILD_TAG} composer install --no-interaction'
                }
            }
        }

        stage('PHPUnit: Core') {
            steps {
                script {
                    try {
                        sh 'docker exec ${BUILD_TAG} APP_TEST_SHARD=core SYMFONY_DEPRECATIONS_HELPER=disabled vendor/bin/phpunit --testsuite core --log-junit .build/jenkins-build-phpunit-core.junit.xml'
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
