php自建dockerhub反代；

```nginx
location / {
  try_files $uri $uri/ /index.php?$query_string;
}
```
