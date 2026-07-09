import pandas as pd
from prophet import Prophet
from sqlalchemy import create_engine
import matplotlib.pyplot as plt

# --- MySQL credentials ---
db_user = "root"
db_pass = ""  # your password
db_host = "localhost"
db_name = "pharmacy_db"  # change to your actual database

# --- Create SQLAlchemy engine ---
engine = create_engine(f'mysql+pymysql://{db_user}:{db_pass}@{db_host}/{db_name}')

# --- 1️⃣ Forecast total sales using Prophet ---
query_sales = "SELECT date_created AS ds, total_amount AS y FROM sales"
df_sales = pd.read_sql(query_sales, engine)

# Make sure data is sorted by date
df_sales['ds'] = pd.to_datetime(df_sales['ds'])
df_sales = df_sales.sort_values('ds')

# Initialize and train Prophet model
model = Prophet()
model.fit(df_sales)

# Create future dataframe and forecast
future = model.make_future_dataframe(periods=30)  # next 30 days
forecast = model.predict(future)

# Print forecast
print("=== Sales Forecast ===")
print(forecast[['ds', 'yhat', 'yhat_lower', 'yhat_upper']].tail())

# Plot forecast
fig1 = model.plot(forecast)
plt.title("Sales Forecast (Next 30 Days)")
plt.show()

# --- 2️⃣ Monthly Sales Trend ---
query_monthly = """
SELECT DATE_FORMAT(date_created, '%Y-%m') AS month, SUM(total_amount) AS monthly_sales
FROM sales
GROUP BY month
ORDER BY month
"""
df_monthly = pd.read_sql(query_monthly, engine)
df_monthly['month'] = pd.to_datetime(df_monthly['month'])

# Plot monthly sales trend
plt.figure(figsize=(10,5))
plt.plot(df_monthly['month'], df_monthly['monthly_sales'], marker='o')
plt.title("Monthly Sales Trend")
plt.xlabel("Month")
plt.ylabel("Total Sales")
plt.grid(True)
plt.show()

# --- 3️⃣ Top Selling Categories ---
query_categories = """
SELECT si.category, SUM(si.amount) AS total_sales
FROM sales_items si
JOIN sales s ON si.sale_id = s.id
GROUP BY si.category
ORDER BY total_sales DESC
LIMIT 10
"""
df_categories = pd.read_sql(query_categories, engine)

# Plot top categories
plt.figure(figsize=(10,6))
plt.bar(df_categories['category'], df_categories['total_sales'], color='skyblue')
plt.title("Top Selling Categories")
plt.xlabel("Category")
plt.ylabel("Total Sales")
plt.xticks(rotation=45)
plt.show()
