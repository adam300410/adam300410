# 圖書借還系統

這是一個使用 PHP 開發的圖書借還管理系統。

## 系統需求

- PHP 7.4+
- MySQL 5.7+
- Git
- Composer (用於管理依賴)

## 安裝步驟

1. Clone 專案
```bash
git clone https://github.com/your-username/library-system.git
cd library-system
```

2. 安裝依賴
```bash
composer install
```

3. 設定資料庫
- 複製 `config/db.example.php` 為 `config/db.php`
- 修改 `config/db.php` 中的資料庫連線設定

4. 建立資料庫結構
```bash
mysql -u your_username -p your_database < database.sql
```

## 開發規範

### Git 分支管理

- `main`: 主分支，用於部署生產環境
- `develop`: 開發分支，所有功能開發都從此分支建立
- `feature/*`: 功能分支，用於開發新功能
- `hotfix/*`: 緊急修復分支，用於修復生產環境問題

### 開發流程

1. 從 develop 分支建立新功能分支
```bash
git checkout develop
git pull origin develop
git checkout -b feature/your-feature-name
```

2. 開發完成後，提交程式碼
```bash
git add .
git commit -m "feat: 新增功能描述"
```

3. 推送到遠端倉庫
```bash
git push origin feature/your-feature-name
```

4. 在 GitHub/GitLab 上建立 Pull Request，等待審核

### Commit 訊息規範

使用 [Conventional Commits](https://www.conventionalcommits.org/) 規範：

- `feat`: 新功能
- `fix`: 錯誤修復
- `docs`: 文件更新
- `style`: 程式碼格式調整
- `refactor`: 重構
- `test`: 測試相關
- `chore`: 建置或工具相關

範例：
```
feat: 新增借書功能
fix: 修復搜尋功能的 SQL 注入問題
docs: 更新安裝說明文件
```

### 程式碼規範

1. PHP 程式碼規範
- 遵循 PSR-12 規範
- 使用有意義的變數名稱
- 新增適當的註解說明

2. 資料庫規範
- 資料表名稱使用小寫英文
- 欄位名稱使用小寫英文，單字間用底線連接
- 必須加入適當的索引
- 重要操作需要加入交易機制

3. 安全性規範
- 所有 SQL 查詢必須使用參數化查詢
- 輸出時必須使用 htmlspecialchars 進行 XSS 防護
- 檔案操作必須驗證檔案類型和大小
- Session 相關操作需要防範 CSRF 攻擊

## 目錄結構

```
├── config/             # 設定檔
├── pages/             # 功能頁面
│   ├── book/         # 書籍相關
│   ├── import/       # 匯入功能
│   ├── record/       # 記錄相關
│   └── user/         # 使用者相關
├── css/              # 樣式檔案
├── js/               # JavaScript 檔案
├── vendor/           # Composer 依賴
├── database.sql      # 資料庫結構
└── index.php         # 入口檔案
```

## 問題回報

如發現任何問題，請在 GitHub Issues 中回報，並提供：

1. 問題描述
2. 重現步驟
3. 預期結果
4. 實際結果
5. 相關的錯誤訊息或截圖

## 許可證

本專案採用 MIT 許可證。
