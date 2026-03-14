# Financial Literacy App Backend

## Setup Instructions

### 1. Install Dependencies
```bash
cd backend
npm install
```

### 2. Initialize Database
```bash
npm run init-db
```

### 3. Start the Server
```bash
# Development mode (with auto-restart)
npm run dev

# Production mode
npm start
```

## Database Schema

The app uses SQLite with the following tables:

### Users
- `id` (Primary Key)
- `name` (Text)
- `email` (Unique, Text)
- `password` (Hashed, Text)
- `created_at` (DateTime)
- `updated_at` (DateTime)

### Daily Targets
- `id` (Primary Key)
- `user_id` (Foreign Key → Users)
- `target_amount` (Real)
- `spending_lock_enabled` (Boolean)
- `cooldown_enabled` (Boolean)
- `emergency_override_enabled` (Boolean)
- `created_at` (DateTime)
- `updated_at` (DateTime)

### Expenses
- `id` (Primary Key)
- `user_id` (Foreign Key → Users)
- `amount` (Real)
- `category` (Text)
- `date` (Text)
- `description` (Text, Optional)
- `created_at` (DateTime)

### Emergency Overrides
- `id` (Primary Key)
- `user_id` (Foreign Key → Users)
- `reason` (Text)
- `daily_spending` (Real)
- `daily_target` (Real)
- `created_at` (DateTime)

### Transaction Cooldowns
- `id` (Primary Key)
- `user_id` (Foreign Key → Users)
- `last_transaction_time` (DateTime)
- `created_at` (DateTime)

## API Endpoints

### Authentication
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login user

### Daily Targets
- `GET /api/daily-target` - Get user's daily target
- `PUT /api/daily-target` - Update daily target

### Expenses
- `GET /api/expenses` - Get all expenses (optional date filter)
- `GET /api/expenses/today` - Get today's expenses
- `POST /api/expenses` - Add new expense
- `DELETE /api/expenses/:id` - Delete expense

### Spending Analysis
- `GET /api/spending/today` - Get today's spending summary
- `GET /api/spending/cooldown` - Check cooldown status

### Emergency Overrides
- `POST /api/emergency-override` - Log emergency override
- `GET /api/emergency-override` - Get emergency override history

## Sample User
For testing, a sample user is created:
- Email: `test@example.com`
- Password: `test123`

## Security Features
- JWT authentication
- Password hashing with bcrypt
- CORS enabled
- SQL injection protection with parameterized queries

## Environment Variables
Create a `.env` file with:
```
PORT=3001
JWT_SECRET=your-secret-key
NODE_ENV=development
```

## Frontend Integration
The frontend should:
1. Send JWT token in Authorization header: `Bearer <token>`
2. Use the API endpoints instead of localStorage
3. Handle authentication state properly
