const sqlite3 = require('sqlite3').verbose();
const bcrypt = require('bcryptjs');
const path = require('path');

// Create database file path
const dbPath = path.join(__dirname, 'financial_literacy.db');

// Initialize database
function initDatabase() {
    const db = new sqlite3.Database(dbPath, (err) => {
        if (err) {
            console.error('Error opening database:', err.message);
            return;
        }
        console.log('Connected to SQLite database.');
    });

    // Enable foreign keys
    db.run('PRAGMA foreign_keys = ON');

    // Create tables
    db.serialize(() => {
        // Users table
        db.run(`
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        `, (err) => {
            if (err) {
                console.error('Error creating users table:', err.message);
            } else {
                console.log('Users table created or already exists.');
            }
        });

        // Daily targets table
        db.run(`
            CREATE TABLE IF NOT EXISTS daily_targets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                target_amount REAL NOT NULL,
                spending_lock_enabled BOOLEAN DEFAULT 0,
                cooldown_enabled BOOLEAN DEFAULT 0,
                emergency_override_enabled BOOLEAN DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            )
        `, (err) => {
            if (err) {
                console.error('Error creating daily_targets table:', err.message);
            } else {
                console.log('Daily targets table created or already exists.');
            }
        });

        // Expenses table
        db.run(`
            CREATE TABLE IF NOT EXISTS expenses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                amount REAL NOT NULL,
                category TEXT NOT NULL,
                date TEXT NOT NULL,
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            )
        `, (err) => {
            if (err) {
                console.error('Error creating expenses table:', err.message);
            } else {
                console.log('Expenses table created or already exists.');
            }
        });

        // Emergency overrides table
        db.run(`
            CREATE TABLE IF NOT EXISTS emergency_overrides (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                reason TEXT NOT NULL,
                daily_spending REAL NOT NULL,
                daily_target REAL NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            )
        `, (err) => {
            if (err) {
                console.error('Error creating emergency_overrides table:', err.message);
            } else {
                console.log('Emergency overrides table created or already exists.');
            }
        });

        // Transaction cooldowns table
        db.run(`
            CREATE TABLE IF NOT EXISTS transaction_cooldowns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                last_transaction_time DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            )
        `, (err) => {
            if (err) {
                console.error('Error creating transaction_cooldowns table:', err.message);
            } else {
                console.log('Transaction cooldowns table created or already exists.');
            }
        });

        // Create sample user for testing
        const hashedPassword = bcrypt.hashSync('test123', 10);
        const sampleUser = {
            name: 'Test User',
            email: 'test@example.com',
            password: hashedPassword
        };

        db.run(`
            INSERT OR IGNORE INTO users (name, email, password)
            VALUES (?, ?, ?)
        `, [sampleUser.name, sampleUser.email, sampleUser.password], function(err) {
            if (err) {
                console.error('Error creating sample user:', err.message);
            } else if (this.changes > 0) {
                console.log('Sample user created: test@example.com / test123');
                
                // Create sample daily target for the sample user
                db.run(`
                    INSERT OR IGNORE INTO daily_targets (user_id, target_amount, spending_lock_enabled, cooldown_enabled, emergency_override_enabled)
                    VALUES (?, ?, ?, ?, ?)
                `, [this.lastID, 50.00, 1, 1, 1], function(err) {
                    if (err) {
                        console.error('Error creating sample daily target:', err.message);
                    } else {
                        console.log('Sample daily target created: $50.00');
                    }
                });
            }
        });

        // Create indexes for better performance
        db.run('CREATE INDEX IF NOT EXISTS idx_expenses_user_date ON expenses (user_id, date)');
        db.run('CREATE INDEX IF NOT EXISTS idx_expenses_date ON expenses (date)');
        db.run('CREATE INDEX IF NOT EXISTS idx_daily_targets_user ON daily_targets (user_id)');
        db.run('CREATE INDEX IF NOT EXISTS idx_emergency_overrides_user ON emergency_overrides (user_id)');
        db.run('CREATE INDEX IF NOT EXISTS idx_transaction_cooldowns_user ON transaction_cooldowns (user_id)');

        console.log('Database indexes created.');
    });

    // Close connection after setup
    db.close((err) => {
        if (err) {
            console.error('Error closing database:', err.message);
        } else {
            console.log('Database initialization completed.');
        }
    });
}

// Run initialization if this file is executed directly
if (require.main === module) {
    initDatabase();
}

module.exports = { initDatabase, dbPath };
