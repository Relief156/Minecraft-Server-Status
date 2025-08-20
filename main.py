import requests
import time
from datetime import datetime

def check_server_status(url):
    try:
        response = requests.get(url)
        # 获取当前时间
        current_time = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        # 打印访问时间和状态码
        print(f"{current_time} - 访问 {url} - 状态码: {response.status_code}")
    except requests.exceptions.RequestException as e:
        # 如果请求失败，打印错误信息
        current_time = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        print(f"{current_time} - 请求失败 - 错误: {e}")

def main():
    url = "http://9gn5if.mcfuns.cn/"
    interval = 300  # 5分钟的秒数
    while True:
        check_server_status(url)
        time.sleep(interval)

if __name__ == "__main__":
    main()