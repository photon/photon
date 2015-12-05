
1. Download and install composer, See https://getcomposer.org/download/
2. Initialize the project

    composer install

3. Initialize the mongrel2 configuration

    m2sh load --config mongrel2/conf/http.conf

4. Start mongrel2

    m2sh start -name http

5. Start the photon project

    ./vendor/bin/hnu serve

6. Open a browser

    http://127.0.0.1:6767/
