```mermaid
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

    ORDERS ||--|{ ORDER_ITEMS : contains


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
        int order_id PK
        int user_id FK
        decimal total_price
        varchar status
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