# Build and run
```
docker-compose up -d --build
```

# Order to run
First run **receive file**, then **send file**.

# Receive file
from the RabbitMQ and put it to the ./src with the same receive.txt file name
```
docker-compose exec php sh -c 'php receive.php'
```

# Send file
file.txt from folder ./src
```
docker-compose exec php sh -c 'php send.php file.txt'
```
