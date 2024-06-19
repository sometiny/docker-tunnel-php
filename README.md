php自建dockerhub反代；

```nginx
location / {
  try_files $uri /index.php?$query_string;
}
```

### /etc/docker/daemon.json ###
`registry-mirrors` 节点增加或只保留你自己的服务地址
```json
{
  "registry-mirrors":["你自己的服务地址"]
}
```
