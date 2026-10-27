erDiagram

    USERS ||--o| USER_ADDRESSES : has
    USERS ||--o{ ORDERS : places
    USERS ||--o{ ADMIN_LOGIN_VERIFICATIONS : has

    CATEGORIES ||--o{ PRODUCTS : categorizes

    PRODUCTS ||--o{ PRODUCT_IMAGES : has
    PRODUCTS ||--o{ PRODUCT_PRICE_HISTORY : has
    PRODUCTS ||--o{ PRODUCT_SPECS : has
    PRODUCTS ||--o{ PRODUCT_ACCESSORIES : has
    PRODUCTS ||--o{ ORDER_ITEMS : contains
    PRODUCTS ||--o{ CART_ITEMS : added_to

    ORDERS ||--|{ ORDER_ITEMS : contains
    ORDERS ||--o{ ORDER_MAIL_LOGS : logs

    CARTS ||--o{ CART_ITEMS : contains

    USERS {
        int user_id PK
        varchar name
        varchar email
        varchar password
        tinyint is_admin "tinyint(1) / DEFAULT 0"
        datetime created_at
        datetime updated_at
    }

    ADMIN_LOGIN_VERIFICATIONS {
        bigint verification_id PK
        int user_id FK
        varchar code_hash
        datetime expires_at
        tinyint attempts "DEFAULT 0"
        datetime used_at "NULL"
        datetime created_at
    }

    USER_ADDRESSES {
        int address_id PK
        int user_id FK
        varchar postal_code
        varchar prefecture
        varchar city
        varchar address_line
        varchar building
        varchar recipient_name
        varchar phone_number
        datetime created_at
        datetime updated_at
    }

    CATEGORIES {
        int category_id PK
        varchar name
        int sort_order "UNSIGNED DEFAULT 0"
        varchar icon_url "varchar(500)"
        datetime created_at
        datetime updated_at
    }

    PRODUCTS {
        int product_id PK
        int category_id FK
        varchar manufacturer "NULL"
        varchar name
        text description
        decimal price
        decimal tax_rate
        varchar status
        varchar product_condition
        int stock_quantity "DEFAULT 0"
        text staff_comment "NULL"
        boolean is_deleted
        tinyint is_recommended "tinyint(1)"
        datetime created_at
        datetime updated_at
    }

    PRODUCT_IMAGES {
        int image_id PK
        int product_id FK
        varchar image_url
        varchar image_type "main / condition, DEFAULT main"
        int sort_order
        datetime created_at
    }

    PRODUCT_SPECS {
        int spec_id PK
        int product_id FK
        varchar spec_key
        varchar spec_value
        int sort_order
        datetime created_at
    }

    PRODUCT_ACCESSORIES {
        int accessory_id PK
        int product_id FK
        varchar content
        int sort_order
        datetime created_at
    }

    PRODUCT_PRICE_HISTORY {
        int price_history_id PK
        int product_id FK
        decimal price
        decimal tax_rate
        datetime created_at
    }

    ORDERS {
        int order_id PK "UNSIGNED AUTO_INCREMENT"
        int user_id FK "UNSIGNED"
        decimal total_price "decimal(10,2)"
        varchar status "varchar(50) / 注文済み・発送済み・受取完了・キャンセル"
        varchar shipping_carrier "NULL / 配送会社"
        varchar tracking_number "NULL / 追跡番号"
        datetime shipped_at "NULL / 発送日時"
        datetime shipment_mail_sent_at "NULL / 発送通知メール送信日時（NULL=未送信）"
        char receipt_token_hash "NULL / CHAR(64) / 受取確認トークンのSHA-256"
        datetime received_at "NULL / 受取確認日時"
        varchar shipping_postal_code
        varchar shipping_prefecture
        varchar shipping_city
        varchar shipping_address_line
        varchar shipping_building
        varchar shipping_recipient_name
        varchar shipping_phone_number
        datetime ordered_at
        datetime updated_at
    }

    ORDER_ITEMS {
        int order_item_id PK
        int order_id FK
        int product_id FK
        decimal price
        datetime created_at
    }

    ORDER_MAIL_LOGS {
        bigint log_id PK "UNSIGNED AUTO_INCREMENT"
        int order_id FK "UNSIGNED"
        varchar mail_type "例: shipment"
        varchar recipient
        varchar status "sent / failed"
        varchar error_message "NULL"
        datetime created_at
    }

    CARTS {
        bigint cart_id PK "UNSIGNED AUTO_INCREMENT"
        char token_hash UK "CHAR(64)"
        datetime expires_at
        datetime created_at
        datetime updated_at
    }

    CART_ITEMS {
        bigint cart_id PK,FK "UNSIGNED"
        int product_id PK,FK
        datetime created_at
    }