erDiagram
    USERS ||--o| USER_ADDRESSES : has
    USERS ||--o{ ORDERS : places
    USERS ||--o{ ADMIN_LOGIN_VERIFICATIONS : verifies
    USERS ||--o| PASSWORD_RESET_TOKENS : requests
    CATEGORIES ||--o{ PRODUCTS : categorizes
    SHIPPING_RATES ||--o{ PRODUCTS : defines_shipping_for
    SHIPPING_RATES o|--o{ ORDERS : applies_to
    PRODUCTS ||--o{ PRODUCT_IMAGES : has
    PRODUCTS ||--o{ PRODUCT_PRICE_HISTORY : records
    PRODUCTS ||--o{ PRODUCT_SPECS : describes
    PRODUCTS ||--o{ PRODUCT_ACCESSORIES : includes
    PRODUCTS ||--o{ INVENTORY_PURCHASES : replenished_by
    ORDERS ||--o{ ORDER_ITEMS : contains
    PRODUCTS ||--o{ ORDER_ITEMS : ordered_as

    USERS {
        int user_id PK
        varchar name
        varchar icon_url "NULL"
        varchar email UK
        varchar password
        boolean is_admin
        datetime created_at
        datetime updated_at
    }

    USER_ADDRESSES {
        int address_id PK
        int user_id FK,UK
        varchar postal_code
        varchar prefecture
        varchar city
        varchar address_line
        varchar building "NULL"
        varchar recipient_name
        varchar phone_number
        datetime created_at
        datetime updated_at
    }

    ADMIN_LOGIN_VERIFICATIONS {
        bigint verification_id PK
        int user_id FK
        varchar code_hash
        datetime expires_at
        tinyint attempts
        datetime used_at "NULL"
        datetime created_at
    }

    PASSWORD_RESET_TOKENS {
        int user_id PK,FK
        char token_hash UK
        datetime expires_at
        datetime created_at
    }

    CATEGORIES {
        int category_id PK
        varchar name UK
        varchar icon_url "NULL"
        int sort_order
        datetime created_at
        datetime updated_at
    }

    SHIPPING_RATES {
        int shipping_rate_id PK
        varchar shipping_size UK
        int sort_order UK
        decimal shipping_fee
        boolean is_active
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
        varchar shipping_size FK
        varchar status
        varchar product_condition
        int stock_quantity
        decimal average_cost "NULL until initialized"
        text staff_comment "NULL"
        boolean is_deleted
        boolean is_recommended
        datetime created_at
        datetime updated_at
    }

    PRODUCT_IMAGES {
        int image_id PK
        int product_id FK
        varchar image_url
        varchar image_type
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

    ORDERS {
        int order_id PK
        char order_number UK
        int user_id FK
        decimal subtotal
        decimal shipping_fee
        varchar shipping_size "NULL"
        int shipping_rate_id FK "NULL"
        decimal total_price
        varchar status
        varchar payment_method
        varchar payment_status
        datetime payment_due_at "NULL"
        datetime paid_at "NULL"
        varchar shipping_postal_code
        varchar shipping_prefecture
        varchar shipping_city
        varchar shipping_address_line
        varchar shipping_building "NULL"
        varchar shipping_recipient_name
        varchar shipping_phone_number
        varchar shipping_carrier "NULL"
        varchar tracking_number "NULL"
        datetime shipped_at "NULL"
        datetime shipment_mail_sent_at "NULL"
        char receipt_token_hash "NULL"
        datetime received_at "NULL"
        datetime ordered_at
        datetime updated_at
    }

    ORDER_ITEMS {
        int order_item_id PK
        int order_id FK
        int product_id FK
        decimal price
        decimal cost_price "NULL when unknown"
        datetime created_at
    }

    INVENTORY_PURCHASES {
        bigint purchase_id PK
        int product_id FK
        enum record_type
        int quantity
        decimal unit_cost
        decimal total_cost
        date purchase_date
        varchar notes "NULL"
        datetime created_at
    }

    OPERATING_EXPENSES {
        bigint expense_id PK
        date expense_date
        varchar category
        varchar description
        decimal amount
        datetime created_at
    }

    BANK_TRANSFER_SETTINGS {
        int settings_id PK
        varchar bank_name
        varchar branch_name
        varchar account_type
        varchar account_number
        varchar account_holder
        datetime updated_at
    }
