```
# 复制app.conf到程序目录，可编辑app.conf修改端口
# 运行服务
./docker-tunnel
```
nginx作反向代理到服务即可。
nginx-rproxy.conf里面是反代的指令。