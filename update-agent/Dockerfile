FROM python:3.12-alpine

RUN apk add --no-cache bash docker-cli docker-cli-compose git
WORKDIR /opt/update-agent
COPY server.py ./server.py
EXPOSE 19100
CMD ["python3", "server.py"]
