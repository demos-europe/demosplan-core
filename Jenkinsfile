def containerName = "demosdeutschland/demosplan-development:4.5"

pipeline {
    agent any

    stages {
        stage('Prepare') {
            steps {
                script{
                    sh 'mkdir -p .build'
                    sh 'mkdir -p .cache'
                    sh 'dp git:init --depth=2'
                }
            }
        }

        stage('Setup Container') {
            steps {
                script {
                    sh """
                        docker run -d --name ${BUILD_TAG} \
                            -v $WORKSPACE:/srv/www -v /var/cache/demosplanCI/:/srv/www/.cache/ \
                            --env CURRENT_HOST_USERNAME=$CONTAINER_USER_NAME \
                            --env CURRENT_HOST_USERID=$CONTAINER_USER_ID \
                            -w /srv/www \
                            $containerName
                    """

                    sh 'sleep 10' // maybe we don't even need this?
                    sh 'docker exec --user $CONTAINER_USER_NAME ${BUILD_TAG} pwd'
                    sh 'docker exec --user $CONTAINER_USER_NAME ${BUILD_TAG} yarn add file:client/ui'
                    sh 'docker exec --user $CONTAINER_USER_NAME ${BUILD_TAG} yarn install --prefer-offline --frozen-lockfile'
                    sh 'docker exec --user $CONTAINER_USER_NAME ${BUILD_TAG} composer install --no-interaction'
                }
            }
        }

        stage('PHPUnit: Core') {
            steps {
                script {
                    try {
                        sh """
                        docker exec --user $CONTAINER_USER_NAME ${BUILD_TAG} rm -rf /tmp/core-application
                        docker exec --user $CONTAINER_USER_NAME ${BUILD_TAG} /bin/zsh -c "APP_TEST_SHARD=core SYMFONY_DEPRECATIONS_HELPER=disabled vendor/bin/phpunit --testsuite core --log-junit .build/jenkins-build-phpunit-core.junit.xml"
                        """
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
