const express = require('express');
const sqlite3 = require('sqlite3').verbose();
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const cors = require('cors');
const path = require('path');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT || 3001;

// Middleware
app.use(cors());
app.use(express.json());

// Database connection
const dbPath = path.join(__dirname, 'database', 'financial_literacy.db');
const db = new sqlite3.Database(dbPath, (err) => {
    if (err) {
        console.error('Error opening database:', err.message);
    } else {
        console.log('Connected to SQLite database.');
        // Enable foreign keys
        db.run('PRAGMA foreign_keys = ON');
    }
});

// JWT Secret
const JWT_SECRET = process.env.JWT_SECRET || 'your-secret-key-change-in-production';

// Middleware to verify JWT token
function authenticateToken(req, res, next) {
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1];

    if (!token) {
        return res.status(401).json({ error: 'Access token required' });
    }

    jwt.verify(token, JWT_SECRET, (err, user) => {
        if (err) {
            return res.status(403).json({ error: 'Invalid token' });
        }
        req.user = user;
        next();
    });
}

// Helper function to get today's date string
function getTodayDateString() {
    return new Date().toISOString().split('T')[0];
}

// ===== AUTH ROUTES =====

// Register
app.post('/api/auth/register', async (req, res) => {
    const { name, email, password } = req.body;

    if (!name || !email || !password) {
        return res.status(400).json({ error: 'All fields are required' });
    }

    try {
        // Check if user already exists
        db.get('SELECT id FROM users WHERE email = ?', [email], async (err, row) => {
            if (err) {
                return res.status(500).json({ error: 'Database error' });
            }

            if (row) {
                return res.status(400).json({ error: 'User already exists' });
            }

            // Hash password
            const hashedPassword = await bcrypt.hash(password, 10);

            // Create user
            db.run('INSERT INTO users (name, email, password) VALUES (?, ?, ?)', 
                [name, email, hashedPassword], function(err) {
                if (err) {
                    return res.status(500).json({ error: 'Error creating user' });
                }

                // Create default daily target
                db.run('INSERT INTO daily_targets (user_id, target_amount, spending_lock_enabled, cooldown_enabled, emergency_override_enabled) VALUES (?, ?, ?, ?, ?)',
                    [this.lastID, 50.00, 0, 0, 0], function(err) {
                    if (err) {
                        console.error('Error creating default daily target:', err);
                    }
                });

                // Generate JWT token
                const token = jwt.sign({ userId: this.lastID, email, name }, JWT_SECRET);
                
                res.status(201).json({
                    message: 'User created successfully',
                    token,
                    user: { id: this.lastID, name, email }
                });
            });
        });
    } catch (error) {
        res.status(500).json({ error: 'Server error' });
    }
});

// Login
app.post('/api/auth/login', (req, res) => {
    const { email, password } = req.body;

    if (!email || !password) {
        return res.status(400).json({ error: 'Email and password are required' });
    }

    db.get('SELECT * FROM users WHERE email = ?', [email], async (err, user) => {
        if (err) {
            return res.status(500).json({ error: 'Database error' });
        }

        if (!user) {
            return res.status(401).json({ error: 'Invalid credentials' });
        }

        // Check password
        const validPassword = await bcrypt.compare(password, user.password);
        if (!validPassword) {
            return res.status(401).json({ error: 'Invalid credentials' });
        }

        // Generate JWT token
        const token = jwt.sign({ userId: user.id, email: user.email, name: user.name }, JWT_SECRET);
        
        res.json({
            message: 'Login successful',
            token,
            user: { id: user.id, name: user.name, email: user.email }
        });
    });
});

// ===== DAILY TARGET ROUTES =====

// Get daily target
app.get('/api/daily-target', authenticateToken, (req, res) => {
    const userId = req.user.userId;

    db.get('SELECT * FROM daily_targets WHERE user_id = ?', [userId], (err, target) => {
        if (err) {
            return res.status(500).json({ error: 'Database error' });
        }

        if (!target) {
            return res.status(404).json({ error: 'Daily target not found' });
        }

        res.json({
            target_amount: target.target_amount,
            spending_lock_enabled: Boolean(target.spending_lock_enabled),
            cooldown_enabled: Boolean(target.cooldown_enabled),
            emergency_override_enabled: Boolean(target.emergency_override_enabled)
        });
    });
});

// Update daily target
app.put('/api/daily-target', authenticateToken, (req, res) => {
    const userId = req.user.userId;
    const { target_amount, spending_lock_enabled, cooldown_enabled, emergency_override_enabled } = req.body;

    db.run(`UPDATE daily_targets SET 
        target_amount = ?, 
        spending_lock_enabled = ?, 
        cooldown_enabled = ?, 
        emergency_override_enabled = ?,
        updated_at = CURRENT_TIMESTAMP
        WHERE user_id = ?`,
        [target_amount, spending_lock_enabled ? 1 : 0, cooldown_enabled ? 1 : 0, emergency_override_enabled ? 1 : 0, userId],
        function(err) {
        if (err) {
            return res.status(500).json({ error: 'Database error' });
        }

        if (this.changes === 0) {
            return res.status(404).json({ error: 'Daily target not found' });
        }

        res.json({ message: 'Daily target updated successfully' });
    });
});

// ===== EXPENSE ROUTES =====

// Get all expenses for a user
app.get('/api/expenses', authenticateToken, (req, res) => {
    const userId = req.user.userId;
    const { date } = req.query;

    let query = 'SELECT * FROM expenses WHERE user_id = ?';
    let params = [userId];

    if (date) {
        query += ' AND date = ?';
        params.push(date);
    }

    query += ' ORDER BY created_at DESC';

    db.all(query, params, (err, expenses) => {
        if (err) {
            return res.status(500).json({ error: 'Database error' });
        }

        res.json(expenses);
    });
});

// Get today's expenses
app.get('/api/expenses/today', authenticateToken, (req, res) => {
    const userId = req.user.userId;
    const today = getTodayDateString();

    db.all('SELECT * FROM expenses WHERE user_id = ? AND date = ? ORDER BY created_at DESC', 
        [userId, today], (err, expenses) => {
        if (err) {
            return res.status(500).json({ error: 'Database error' });
        }

        res.json(expenses);
    });
});

// Add expense
app.post('/api/expenses', authenticateToken, (req, res) => {
    const userId = req.user.userId;
    const { amount, category, date, description } = req.body;

    if (!amount || !category) {
        return res.status(400).json({ error: 'Amount and category are required' });
    }

    const expenseDate = date || getTodayDateString();

    db.run('INSERT INTO expenses (user_id, amount, category, date, description) VALUES (?, ?, ?, ?, ?)',
        [userId, amount, category, expenseDate, description || null], function(err) {
        if (err) {
            return res.status(500).json({ error: 'Database error' });
        }

        // Update last transaction time for cooldown
        db.run('INSERT OR REPLACE INTO transaction_cooldowns (user_id, last_transaction_time) VALUES (?, ?)',
            [userId, new Date().toISOString()], function(err) {
            if (err) {
                console.error('Error updating transaction cooldown:', err);
            }
        });

        res.status(201).json({
            message: 'Expense added successfully',
            expense: {
                id: this.lastID,
                user_id: userId,
                amount,
                category,
                date: expenseDate,
                description,
                created_at: new Date().toISOString()
            }
        });
    });
});

// Delete expense
app.delete('/api/expenses/:id', authenticateToken, (req, res) => {
    const userId = req.user.userId;
    const expenseId = req.params.id;

    db.run('DELETE FROM expenses WHERE id = ? AND user_id = ?', [expenseId, userId], function(err) {
        if (err) {
            return res.status(500).json({ error: 'Database error' });
        }

        if (this.changes === 0) {
            return res.status(404).json({ error: 'Expense not found' });
        }

        res.json({ message: 'Expense deleted successfully' });
    });
});

// ===== SPENDING ANALYSIS ROUTES =====

// Get today's spending summary
app.get('/api/spending/today', authenticateToken, (req, res) => {
    const userId = req.user.userId;
    const today = getTodayDateString();

    db.get(`
        SELECT 
            COALESCE(SUM(amount), 0) as total_spent,
            COUNT(*) as transaction_count
        FROM expenses 
        WHERE user_id = ? AND date = ?
    `, [userId, today], (err, result) => {
        if (err) {
            return res.status(500).json({ error: 'Database error' });
        }

        res.json({
            total_spent: result.total_spent,
            transaction_count: result.transaction_count,
            date: today
        });
    });
});

// Check cooldown status
app.get('/api/spending/cooldown', authenticateToken, (req, res) => {
    const userId = req.user.userId;

    db.get('SELECT last_transaction_time FROM transaction_cooldowns WHERE user_id = ?', [userId], (err, result) => {
        if (err) {
            return res.status(500).json({ error: 'Database error' });
        }

        let inCooldown = false;
        let remainingMinutes = 0;

        if (result && result.last_transaction_time) {
            const lastTransaction = new Date(result.last_transaction_time);
            const now = new Date();
            const timeDiff = now - lastTransaction;
            const cooldownPeriod = 30 * 60 * 1000; // 30 minutes

            if (timeDiff < cooldownPeriod) {
                inCooldown = true;
                remainingMinutes = Math.ceil((cooldownPeriod - timeDiff) / (60 * 1000));
            }
        }

        res.json({
            in_cooldown: inCooldown,
            remaining_minutes: remainingMinutes
        });
    });
});

// ===== EMERGENCY OVERRIDE ROUTES =====

// Log emergency override
app.post('/api/emergency-override', authenticateToken, (req, res) => {
    const userId = req.user.userId;
    const { reason, daily_spending, daily_target } = req.body;

    if (!reason) {
        return res.status(400).json({ error: 'Reason is required' });
    }

    db.run('INSERT INTO emergency_overrides (user_id, reason, daily_spending, daily_target) VALUES (?, ?, ?, ?)',
        [userId, reason, daily_spending, daily_target], function(err) {
        if (err) {
            return res.status(500).json({ error: 'Database error' });
        }

        res.status(201).json({
            message: 'Emergency override logged successfully',
            id: this.lastID
        });
    });
});

// Get emergency override history
app.get('/api/emergency-override', authenticateToken, (req, res) => {
    const userId = req.user.userId;

    db.all('SELECT * FROM emergency_overrides WHERE user_id = ? ORDER BY created_at DESC LIMIT 10',
        [userId], (err, overrides) => {
        if (err) {
            return res.status(500).json({ error: 'Database error' });
        }

        res.json(overrides);
    });
});

// Start server
app.listen(PORT, () => {
    console.log(`Server running on port ${PORT}`);
    console.log(`API available at http://localhost:${PORT}/api`);
});

// Handle graceful shutdown
process.on('SIGINT', () => {
    console.log('\nShutting down gracefully...');
    db.close((err) => {
        if (err) {
            console.error('Error closing database:', err.message);
        } else {
            console.log('Database connection closed.');
        }
        process.exit(0);
    });
});
