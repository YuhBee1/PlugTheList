<?php
declare(strict_types=1);

   
                                                                 
                                                                                                                                 
                                                                                                   
                                                                                                                                                    
   
return [
    'tables' => [
        'settings' => 'CREATE TABLE IF NOT EXISTS settings (k VARCHAR(60) NOT NULL PRIMARY KEY, v TEXT NOT NULL, updated_at DATETIME NULL){ENGINE}',

        'users' => 'CREATE TABLE IF NOT EXISTS users (
            id {PK},
            role VARCHAR(12) NOT NULL,
            email VARCHAR(190) NOT NULL,
            email_verified_at DATETIME NULL,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(120) NOT NULL,
            display_name VARCHAR(80) NULL,
            phone VARCHAR(20) NULL,
            creative_type VARCHAR(20) NULL,
            business_name VARCHAR(120) NULL,
            status VARCHAR(12) NOT NULL DEFAULT \'active\',
            totp_secret TEXT NULL,
            totp_enabled TINYINT NOT NULL DEFAULT 0,
            totp_last_step BIGINT NOT NULL DEFAULT 0,
            backup_codes TEXT NULL,
            terms_version VARCHAR(20) NULL,
            terms_accepted_at DATETIME NULL,
            marketing_opt_in TINYINT NOT NULL DEFAULT 0,
            last_login_at DATETIME NULL,
            last_login_ip VARCHAR(45) NULL,
            deleted_at DATETIME NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        ){ENGINE}',

        'sessions' => 'CREATE TABLE IF NOT EXISTS sessions (
            id CHAR(64) NOT NULL PRIMARY KEY,
            user_id INT NOT NULL,
            csrf CHAR(64) NOT NULL,
            mfa_ok TINYINT NOT NULL DEFAULT 0,
            ua VARCHAR(200) NULL,
            ip VARCHAR(45) NULL,
            last_seen_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            revoked_at DATETIME NULL,
            created_at DATETIME NULL
        ){ENGINE}',

        'email_tokens' => 'CREATE TABLE IF NOT EXISTS email_tokens (
            id {PK},
            user_id INT NOT NULL,
            purpose VARCHAR(20) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NULL
        ){ENGINE}',

        'rate_limits' => 'CREATE TABLE IF NOT EXISTS rate_limits (k CHAR(64) NOT NULL PRIMARY KEY, hits INT NOT NULL, window_start INT NOT NULL){ENGINE}',

        'audit_logs' => 'CREATE TABLE IF NOT EXISTS audit_logs (
            id {PK},
            user_id INT NULL,
            action VARCHAR(60) NOT NULL,
            entity VARCHAR(40) NULL,
            entity_id INT NULL,
            ip VARCHAR(45) NULL,
            meta TEXT NULL,
            created_at DATETIME NULL
        ){ENGINE}',

        'webhook_events' => 'CREATE TABLE IF NOT EXISTS webhook_events (
            id {PK},
            provider VARCHAR(20) NOT NULL,
            event_key VARCHAR(120) NOT NULL,
            status VARCHAR(12) NOT NULL DEFAULT \'received\',
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        ){ENGINE}',

        'listings' => 'CREATE TABLE IF NOT EXISTS listings (
            id {PK},
            curator_id INT NOT NULL,
            platform VARCHAR(20) NOT NULL,
            title VARCHAR(120) NOT NULL,
            url VARCHAR(300) NOT NULL,
            followers INT NOT NULL DEFAULT 0,
            genres VARCHAR(200) NOT NULL DEFAULT \'\',
            description TEXT NOT NULL,
            cover_file_id INT NULL,
            status VARCHAR(12) NOT NULL DEFAULT \'pending\',
            verified TINYINT NOT NULL DEFAULT 0,
            verify_code VARCHAR(20) NULL,
            verified_at DATETIME NULL,
            reject_reason VARCHAR(300) NULL,
            max_active_orders INT NOT NULL DEFAULT 5,
            orders_done INT NOT NULL DEFAULT 0,
            rating_sum INT NOT NULL DEFAULT 0,
            rating_count INT NOT NULL DEFAULT 0,
            min_price_kobo BIGINT NOT NULL DEFAULT 0,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        ){ENGINE}',

        'listing_offers' => 'CREATE TABLE IF NOT EXISTS listing_offers (
            id {PK},
            listing_id INT NOT NULL,
            service VARCHAR(12) NOT NULL,
            price_kobo BIGINT NOT NULL,
            turnaround_days INT NOT NULL DEFAULT 3,
            details VARCHAR(300) NULL,
            active TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        ){ENGINE}',

        'listing_proofs' => 'CREATE TABLE IF NOT EXISTS listing_proofs (
            id {PK},
            listing_id INT NOT NULL,
            caption VARCHAR(160) NOT NULL,
            link VARCHAR(300) NULL,
            file_id INT NULL,
            created_at DATETIME NULL
        ){ENGINE}',

        'files' => 'CREATE TABLE IF NOT EXISTS files (
            id {PK},
            owner_id INT NOT NULL,
            kind VARCHAR(12) NOT NULL,
            ref_id INT NULL,
            path VARCHAR(120) NOT NULL,
            mime VARCHAR(40) NOT NULL,
            size INT NOT NULL,
            created_at DATETIME NULL
        ){ENGINE}',

        'orders' => 'CREATE TABLE IF NOT EXISTS orders (
            id {PK},
            ref VARCHAR(20) NOT NULL,
            creative_id INT NOT NULL,
            curator_id INT NOT NULL,
            listing_id INT NOT NULL,
            offer_id INT NOT NULL,
            platform VARCHAR(20) NOT NULL,
            service VARCHAR(12) NOT NULL,
            listing_title VARCHAR(120) NOT NULL,
            track_title VARCHAR(140) NOT NULL,
            track_url VARCHAR(300) NOT NULL,
            brief TEXT NULL,
            price_kobo BIGINT NOT NULL,
            buyer_fee_kobo BIGINT NOT NULL,
            curator_fee_kobo BIGINT NOT NULL,
            total_kobo BIGINT NOT NULL,
            turnaround_days INT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'awaiting_payment\',
            paid_at DATETIME NULL,
            accept_by DATETIME NULL,
            accepted_at DATETIME NULL,
            due_at DATETIME NULL,
            delivered_at DATETIME NULL,
            review_by DATETIME NULL,
            completed_at DATETIME NULL,
            delivery_url VARCHAR(300) NULL,
            delivery_note TEXT NULL,
            delivery_file_id INT NULL,
            dispute_by INT NULL,
            dispute_reason TEXT NULL,
            disputed_at DATETIME NULL,
            resolution_note TEXT NULL,
            expires_at DATETIME NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        ){ENGINE}',

        'order_events' => 'CREATE TABLE IF NOT EXISTS order_events (
            id {PK},
            order_id INT NOT NULL,
            user_id INT NULL,
            event VARCHAR(30) NOT NULL,
            note VARCHAR(500) NULL,
            created_at DATETIME NULL
        ){ENGINE}',

        'order_messages' => 'CREATE TABLE IF NOT EXISTS order_messages (
            id {PK},
            order_id INT NOT NULL,
            user_id INT NOT NULL,
            body TEXT NOT NULL,
            created_at DATETIME NULL
        ){ENGINE}',

        'reviews' => 'CREATE TABLE IF NOT EXISTS reviews (
            id {PK},
            order_id INT NOT NULL,
            listing_id INT NOT NULL,
            creative_id INT NOT NULL,
            rating INT NOT NULL,
            comment VARCHAR(500) NULL,
            created_at DATETIME NULL
        ){ENGINE}',

        'payments' => 'CREATE TABLE IF NOT EXISTS payments (
            id {PK},
            order_id INT NOT NULL,
            reference VARCHAR(60) NOT NULL,
            amount_kobo BIGINT NOT NULL,
            status VARCHAR(12) NOT NULL DEFAULT \'pending\',
            channel VARCHAR(30) NULL,
            paid_at DATETIME NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        ){ENGINE}',

        'ledger_txns' => 'CREATE TABLE IF NOT EXISTS ledger_txns (
            id {PK},
            kind VARCHAR(24) NOT NULL,
            order_id INT NULL,
            idem VARCHAR(80) NOT NULL,
            memo VARCHAR(200) NULL,
            created_at DATETIME NULL
        ){ENGINE}',

        'ledger_lines' => 'CREATE TABLE IF NOT EXISTS ledger_lines (
            id {PK},
            txn_id INT NOT NULL,
            account VARCHAR(40) NOT NULL,
            amount_kobo BIGINT NOT NULL,
            created_at DATETIME NULL
        ){ENGINE}',

        'bank_accounts' => 'CREATE TABLE IF NOT EXISTS bank_accounts (
            id {PK},
            user_id INT NOT NULL,
            bank_code VARCHAR(12) NOT NULL,
            bank_name VARCHAR(80) NOT NULL,
            account_enc TEXT NOT NULL,
            last4 CHAR(4) NOT NULL,
            account_name VARCHAR(120) NOT NULL,
            recipient_code VARCHAR(40) NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        ){ENGINE}',

        'withdrawals' => 'CREATE TABLE IF NOT EXISTS withdrawals (
            id {PK},
            user_id INT NOT NULL,
            amount_kobo BIGINT NOT NULL,
            fee_kobo BIGINT NOT NULL DEFAULT 0,
            status VARCHAR(12) NOT NULL DEFAULT \'requested\',
            bank_name VARCHAR(80) NOT NULL,
            last4 CHAR(4) NOT NULL,
            account_name VARCHAR(120) NOT NULL,
            recipient_code VARCHAR(40) NULL,
            transfer_ref VARCHAR(60) NOT NULL,
            transfer_code VARCHAR(40) NULL,
            note VARCHAR(300) NULL,
            decided_by INT NULL,
            decided_at DATETIME NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        ){ENGINE}',

        'notifications' => 'CREATE TABLE IF NOT EXISTS notifications (
            id {PK},
            user_id INT NOT NULL,
            title VARCHAR(150) NOT NULL,
            body VARCHAR(500) NOT NULL,
            url VARCHAR(300) NULL,
            read_at DATETIME NULL,
            created_at DATETIME NULL
        ){ENGINE}',

        'migrations' => 'CREATE TABLE IF NOT EXISTS migrations (version INT NOT NULL PRIMARY KEY, applied_at DATETIME NULL){ENGINE}',
    ],

                                     
    'indexes' => [
        ['ux_users_email', 'users', 'email', true],
        ['ix_users_role', 'users', 'role, status', false],
        ['ix_sessions_user', 'sessions', 'user_id', false],
        ['ix_tokens_hash', 'email_tokens', 'token_hash', false],
        ['ix_tokens_user', 'email_tokens', 'user_id, purpose', false],
        ['ix_audit_user', 'audit_logs', 'user_id', false],
        ['ix_audit_created', 'audit_logs', 'created_at', false],
        ['ux_webhook_key', 'webhook_events', 'provider, event_key', true],
        ['ix_listings_curator', 'listings', 'curator_id', false],
        ['ix_listings_browse', 'listings', 'status, platform', false],
        ['ix_offers_listing', 'listing_offers', 'listing_id', false],
        ['ix_proofs_listing', 'listing_proofs', 'listing_id', false],
        ['ix_files_owner', 'files', 'owner_id, kind', false],
        ['ux_orders_ref', 'orders', 'ref', true],
        ['ix_orders_creative', 'orders', 'creative_id, status', false],
        ['ix_orders_curator', 'orders', 'curator_id, status', false],
        ['ix_orders_status', 'orders', 'status', false],
        ['ix_events_order', 'order_events', 'order_id', false],
        ['ix_msgs_order', 'order_messages', 'order_id', false],
        ['ux_reviews_order', 'reviews', 'order_id', true],
        ['ix_reviews_listing', 'reviews', 'listing_id', false],
        ['ux_payments_ref', 'payments', 'reference', true],
        ['ix_payments_order', 'payments', 'order_id', false],
        ['ux_ledger_idem', 'ledger_txns', 'idem', true],
        ['ix_ledger_lines_txn', 'ledger_lines', 'txn_id', false],
        ['ix_ledger_lines_acct', 'ledger_lines', 'account', false],
        ['ix_bank_user', 'bank_accounts', 'user_id', false],
        ['ux_withdraw_ref', 'withdrawals', 'transfer_ref', true],
        ['ix_withdraw_user', 'withdrawals', 'user_id, status', false],
        ['ix_notif_user', 'notifications', 'user_id, read_at', false],
    ],

                                                                           
    'columns' => [],
];
