<?php
// API Documentation Page
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BSDO Sale API Documentation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1, h2, h3 {
            color: #333;
        }
        .endpoint {
            margin-bottom: 30px;
            padding: 20px;
            border-left: 4px solid #4e73df;
            background-color: #f8f9fc;
        }
        .method {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            margin-right: 10px;
        }
        .get { background-color: #28a745; }
        .post { background-color: #007bff; }
        .put { background-color: #ffc107; color: #333; }
        .delete { background-color: #dc3545; }
        .param {
            margin: 5px 0;
        }
        .param-name {
            font-weight: bold;
            color: #495057;
        }
        .param-type {
            color: #6c757d;
            font-style: italic;
        }
        .example {
            background-color: #f1f3f4;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>BSDO Sale API Documentation</h1>
        <p>Welcome to the BSDO Sale platform API. This API provides programmatic access to the e-commerce platform's functionality including user management, product listings, inquiries, orders, and live streaming features.</p>
        
        <h2>Authentication</h2>
        <p>Most endpoints require authentication. Use the <code>/api/auth/login</code> endpoint to authenticate and receive a session token.</p>
        
        <h2>Base URL</h2>
        <p>All API requests should be made to: <code>https://your-domain.com/bsdo/api/</code></p>
        
        <h2>Endpoints</h2>
        
        <div class="endpoint">
            <h3>Authentication</h3>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                <h3>/api/auth/login</h3>
                <p>Login to the platform</p>
                
                <h4>Parameters:</h4>
                <div class="param">
                    <span class="param-name">email</span> 
                    <span class="param-type">(string, required)</span> - User email address
                </div>
                <div class="param">
                    <span class="param-name">password</span> 
                    <span class="param-type">(string, required)</span> - User password
                </div>
                <div class="param">
                    <span class="param-name">role</span> 
                    <span class="param-type">(string, optional)</span> - User role ('client', 'seller', 'admin'), defaults to 'client'
                </div>
                <div class="param">
                    <span class="param-name">seller_code</span> 
                    <span class="param-type">(string, optional)</span> - Required for seller role
                </div>
                <div class="param">
                    <span class="param-name">admin_key</span> 
                    <span class="param-type">(string, optional)</span> - Required for admin role
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "message": "Login successful",
    "user": {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "email": "john@example.com",
        "role": "client",
        "store_name": null,
        "business_type": null
    },
    "session": "session_id"
}
                </div>
            </div>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                <h3>/api/auth/register</h3>
                <p>Register a new account</p>
                
                <h4>Parameters:</h4>
                <div class="param">
                    <span class="param-name">first_name</span> 
                    <span class="param-type">(string, required)</span> - User's first name
                </div>
                <div class="param">
                    <span class="param-name">last_name</span> 
                    <span class="param-type">(string, required)</span> - User's last name
                </div>
                <div class="param">
                    <span class="param-name">email</span> 
                    <span class="param-type">(string, required)</span> - User's email
                </div>
                <div class="param">
                    <span class="param-name">password</span> 
                    <span class="param-type">(string, required)</span> - User's password
                </div>
                <div class="param">
                    <span class="param-name">confirm_password</span> 
                    <span class="param-type">(string, required)</span> - Confirmation of password
                </div>
                <div class="param">
                    <span class="param-name">role</span> 
                    <span class="param-type">(string, optional)</span> - User role ('client', 'seller'), defaults to 'client'
                </div>
                <div class="param">
                    <span class="param-name">store_name</span> 
                    <span class="param-type">(string, optional)</span> - Required for seller role
                </div>
                <div class="param">
                    <span class="param-name">business_type</span> 
                    <span class="param-type">(string, optional)</span> - Required for seller role
                </div>
                <div class="param">
                    <span class="param-name">referral_code</span> 
                    <span class="param-type">(string, optional)</span> - Referral code if applicable
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "message": "Registration successful",
    "user": {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "email": "john@example.com",
        "role": "client",
        "store_name": null,
        "business_type": null
    }
}
                </div>
            </div>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                <h3>/api/auth/logout</h3>
                <p>Logout from the platform</p>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "message": "Logout successful"
}
                </div>
            </div>
        </div>
        
        <div class="endpoint">
            <h3>Products</h3>
            
            <div class="endpoint">
                <span class="method get">GET</span>
                <h3>/api/products/list</h3>
                <p>Get a list of products</p>
                
                <h4>Query Parameters:</h4>
                <div class="param">
                    <span class="param-name">page</span> 
                    <span class="param-type">(integer, optional)</span> - Page number, defaults to 1
                </div>
                <div class="param">
                    <span class="param-name">limit</span> 
                    <span class="param-type">(integer, optional)</span> - Items per page, defaults to 12
                </div>
                <div class="param">
                    <span class="param-name">category</span> 
                    <span class="param-type">(string, optional)</span> - Filter by category
                </div>
                <div class="param">
                    <span class="param-name">location</span> 
                    <span class="param-type">(string, optional)</span> - Filter by location
                </div>
                <div class="param">
                    <span class="param-name">min_price</span> 
                    <span class="param-type">(number, optional)</span> - Minimum price filter
                </div>
                <div class="param">
                    <span class="param-name">max_price</span> 
                    <span class="param-type">(number, optional)</span> - Maximum price filter
                </div>
                <div class="param">
                    <span class="param-name">search</span> 
                    <span class="param-type">(string, optional)</span> - Search term
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "products": [...],
    "pagination": {
        "current_page": 1,
        "total_pages": 5,
        "total_items": 50,
        "per_page": 12
    }
}
                </div>
            </div>
            
            <div class="endpoint">
                <span class="method get">GET</span>
                <h3>/api/products/detail</h3>
                <p>Get details for a specific product</p>
                
                <h4>Query Parameters:</h4>
                <div class="param">
                    <span class="param-name">id</span> 
                    <span class="param-type">(integer, required)</span> - Product ID
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "product": {
        "id": 1,
        "name": "Sample Product",
        "price": 29.99,
        "description": "Product description",
        "seller": {...},
        "category": {...}
    }
}
                </div>
            </div>
        </div>
        
        <div class="endpoint">
            <h3>User Profile</h3>
            
            <div class="endpoint">
                <span class="method get">GET</span>
                <h3>/api/users/profile</h3>
                <p>Get current user's profile information</p>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "user": {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "email": "john@example.com",
        "role": "client"
    }
}
                </div>
            </div>
            
            <div class="endpoint">
                <span class="method put">PUT</span>
                <h3>/api/users/profile</h3>
                <p>Update current user's profile information</p>
                
                <h4>Body Parameters:</h4>
                <div class="param">
                    <span class="param-name">first_name</span> 
                    <span class="param-type">(string, required)</span> - Updated first name
                </div>
                <div class="param">
                    <span class="param-name">last_name</span> 
                    <span class="param-type">(string, required)</span> - Updated last name
                </div>
                <div class="param">
                    <span class="param-name">email</span> 
                    <span class="param-type">(string, required)</span> - Updated email
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "message": "Profile updated successfully",
    "user": {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "email": "john.doe@example.com"
    }
}
                </div>
            </div>
        </div>
        
        <div class="endpoint">
            <h3>Inquiries</h3>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                <h3>/api/inquiries/create</h3>
                <p>Create a new inquiry about a product</p>
                
                <h4>Body Parameters:</h4>
                <div class="param">
                    <span class="param-name">product_id</span> 
                    <span class="param-type">(integer, required)</span> - ID of the product
                </div>
                <div class="param">
                    <span class="param-name">message</span> 
                    <span class="param-type">(string, required)</span> - Inquiry message
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "message": "Inquiry sent successfully",
    "inquiry_id": 123
}
                </div>
            </div>
        </div>
        
        <div class="endpoint">
            <h3>Orders</h3>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                <h3>/api/orders/create</h3>
                <p>Create a new order</p>
                
                <h4>Body Parameters:</h4>
                <div class="param">
                    <span class="param-name">product_id</span> 
                    <span class="param-type">(integer, required)</span> - ID of the product to order
                </div>
                <div class="param">
                    <span class="param-name">quantity</span> 
                    <span class="param-type">(integer, required)</span> - Quantity to order
                </div>
                <div class="param">
                    <span class="param-name">payment_method</span> 
                    <span class="param-type">(string, optional)</span> - Payment method, defaults to 'paypal'
                </div>
                <div class="param">
                    <span class="param-name">shipping_address</span> 
                    <span class="param-type">(object, required)</span> - Shipping address object
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "message": "Order created successfully",
    "order": {
        "id": 456,
        "product_id": 1,
        "quantity": 2,
        "total_amount": 59.98,
        "status": "pending"
    }
}
                </div>
            </div>
        </div>
        
        <div class="endpoint">
            <h3>Live Streams</h3>
            
            <div class="endpoint">
                <span class="method get">GET</span>
                <h3>/api/live_streams/list</h3>
                <p>Get a list of live streams</p>
                
                <h4>Query Parameters:</h4>
                <div class="param">
                    <span class="param-name">page</span> 
                    <span class="param-type">(integer, optional)</span> - Page number, defaults to 1
                </div>
                <div class="param">
                    <span class="param-name">limit</span> 
                    <span class="param-type">(integer, optional)</span> - Items per page, defaults to 12
                </div>
                <div class="param">
                    <span class="param-name">category</span> 
                    <span class="param-type">(string, optional)</span> - Filter by category
                </div>
                <div class="param">
                    <span class="param-name">is_live</span> 
                    <span class="param-type">(boolean, optional)</span> - Filter by live status
                </div>
                <div class="param">
                    <span class="param-name">status</span> 
                    <span class="param-type">(string, optional)</span> - Filter by status ('live', 'scheduled', 'ended')
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "streams": [...],
    "pagination": {
        "current_page": 1,
        "total_pages": 3,
        "total_items": 25,
        "per_page": 12
    }
}
                </div>
            </div>
        </div>
        
        <div class="endpoint">
            <h3>Cart</h3>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                <h3>/api/cart/add</h3>
                <p>Add an item to the cart</p>
                
                <h4>Body Parameters:</h4>
                <div class="param">
                    <span class="param-name">product_id</span> 
                    <span class="param-type">(integer, required)</span> - ID of the product to add
                </div>
                <div class="param">
                    <span class="param-name">quantity</span> 
                    <span class="param-type">(integer, optional)</span> - Quantity to add, defaults to 1
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "message": "Item added to cart successfully",
    "cart_item": {...},
    "cart_total_items": 3
}
                </div>
            </div>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                <h3>/api/cart/remove</h3>
                <p>Remove an item from the cart</p>
                
                <h4>Body Parameters:</h4>
                <div class="param">
                    <span class="param-name">product_id</span> 
                    <span class="param-type">(integer, required)</span> - ID of the product to remove
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "message": "Item removed from cart successfully",
    "cart_total_items": 2
}
                </div>
            </div>
            
            <div class="endpoint">
                <span class="method get">GET</span>
                <h3>/api/cart/list</h3>
                <p>Get all items in the cart</p>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "cart_items": [...],
    "total_items": 3,
    "total_amount": 89.97
}
                </div>
            </div>
        </div>
        
        <div class="endpoint">
            <h3>Seller Features</h3>
            
            <div class="endpoint">
                <span class="method get">GET</span>
                <h3>/api/seller/dashboard</h3>
                <p>Get seller dashboard data including stats and recent activity</p>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "dashboard_data": {
        "stats": {
            "active_products": 5,
            "completed_orders": 12,
            "pending_orders": 3,
            "total_revenue": 450.50,
            "total_inquiries": 8
        },
        "recent_orders": [...],
        "recent_inquiries": [...]
    }
}
                </div>
            </div>
            
            <div class="endpoint">
                <span class="method get">GET</span>
                <h3>/api/seller/products</h3>
                <p>Get seller's products with optional filters</p>
                
                <h4>Query Parameters:</h4>
                <div class="param">
                    <span class="param-name">page</span> 
                    <span class="param-type">(integer, optional)</span> - Page number, defaults to 1
                </div>
                <div class="param">
                    <span class="param-name">limit</span> 
                    <span class="param-type">(integer, optional)</span> - Items per page, defaults to 12
                </div>
                <div class="param">
                    <span class="param-name">status</span> 
                    <span class="param-type">(string, optional)</span> - Filter by status ('active', 'inactive', 'all'), defaults to 'all'
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "products": [...],
    "pagination": {
        "current_page": 1,
        "total_pages": 2,
        "total_items": 15,
        "per_page": 12
    }
}
                </div>
            </div>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                <h3>/api/seller/products</h3>
                <p>Create a new product</p>
                
                <h4>Body Parameters:</h4>
                <div class="param">
                    <span class="param-name">name</span> 
                    <span class="param-type">(string, required)</span> - Product name
                </div>
                <div class="param">
                    <span class="param-name">description</span> 
                    <span class="param-type">(string, required)</span> - Product description
                </div>
                <div class="param">
                    <span class="param-name">price</span> 
                    <span class="param-type">(number, required)</span> - Product price
                </div>
                <div class="param">
                    <span class="param-name">stock</span> 
                    <span class="param-type">(integer, required)</span> - Available stock
                </div>
                <div class="param">
                    <span class="param-name">product_type</span> 
                    <span class="param-type">(string, optional)</span> - Type of product ('regular', 'rental'), defaults to 'regular'
                </div>
                <div class="param">
                    <span class="param-name">category_id</span> 
                    <span class="param-type">(integer, optional)</span> - Category ID
                </div>
                <div class="param">
                    <span class="param-name">location</span> 
                    <span class="param-type">(string, optional)</span> - Product location
                </div>
                <div class="param">
                    <span class="param-name">rental_price_per_day</span> 
                    <span class="param-type">(number, optional)</span> - Daily rental price (for rental products)
                </div>
                <div class="param">
                    <span class="param-name">rental_price_per_week</span> 
                    <span class="param-type">(number, optional)</span> - Weekly rental price (for rental products)
                </div>
                <div class="param">
                    <span class="param-name">min_rental_days</span> 
                    <span class="param-type">(integer, optional)</span> - Minimum rental days (for rental products)
                </div>
                <div class="param">
                    <span class="param-name">max_rental_days</span> 
                    <span class="param-type">(integer, optional)</span> - Maximum rental days (for rental products)
                </div>
                <div class="param">
                    <span class="param-name">security_deposit</span> 
                    <span class="param-type">(number, optional)</span> - Security deposit (for rental products)
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "message": "Product created successfully",
    "product_id": 123
}
                </div>
            </div>
            
            <div class="endpoint">
                <span class="method put">PUT</span>
                <h3>/api/seller/products</h3>
                <p>Update a product</p>
                
                <h4>Body Parameters:</h4>
                <div class="param">
                    <span class="param-name">id</span> 
                    <span class="param-type">(integer, required)</span> - Product ID to update
                </div>
                <div class="param">
                    <span class="param-name">name</span> 
                    <span class="param-type">(string, optional)</span> - Updated product name
                </div>
                <div class="param">
                    <span class="param-name">description</span> 
                    <span class="param-type">(string, optional)</span> - Updated product description
                </div>
                <div class="param">
                    <span class="param-name">price</span> 
                    <span class="param-type">(number, optional)</span> - Updated product price
                </div>
                <div class="param">
                    <span class="param-name">stock</span> 
                    <span class="param-type">(integer, optional)</span> - Updated available stock
                </div>
                <div class="param">
                    <span class="param-name">status</span> 
                    <span class="param-type">(string, optional)</span> - Updated status ('active', 'inactive')
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "message": "Product updated successfully"
}
                </div>
            </div>
            
            <div class="endpoint">
                <span class="method delete">DELETE</span>
                <h3>/api/seller/products</h3>
                <p>Delete a product</p>
                
                <h4>Query Parameters:</h4>
                <div class="param">
                    <span class="param-name">id</span> 
                    <span class="param-type">(integer, required)</span> - Product ID to delete
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "message": "Product deleted successfully"
}
                </div>
            </div>
            
            <div class="endpoint">
                <span class="method get">GET</span>
                <h3>/api/seller/orders</h3>
                <p>Get seller's orders with optional filters</p>
                
                <h4>Query Parameters:</h4>
                <div class="param">
                    <span class="param-name">page</span> 
                    <span class="param-type">(integer, optional)</span> - Page number, defaults to 1
                </div>
                <div class="param">
                    <span class="param-name">limit</span> 
                    <span class="param-type">(integer, optional)</span> - Items per page, defaults to 12
                </div>
                <div class="param">
                    <span class="param-name">status</span> 
                    <span class="param-type">(string, optional)</span> - Filter by status ('pending', 'processing', 'shipped', 'delivered', 'completed', 'cancelled', 'all'), defaults to 'all'
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "orders": [...],
    "pagination": {
        "current_page": 1,
        "total_pages": 2,
        "total_items": 15,
        "per_page": 12
    }
}
                </div>
            </div>
            
            <div class="endpoint">
                <span class="method put">PUT</span>
                <h3>/api/seller/orders</h3>
                <p>Update an order status</p>
                
                <h4>Body Parameters:</h4>
                <div class="param">
                    <span class="param-name">order_id</span> 
                    <span class="param-type">(integer, required)</span> - Order ID to update
                </div>
                <div class="param">
                    <span class="param-name">status</span> 
                    <span class="param-type">(string, required)</span> - New status ('pending', 'processing', 'shipped', 'delivered', 'completed', 'cancelled')
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "message": "Order status updated successfully"
}
                </div>
            </div>
            
            <div class="endpoint">
                <span class="method get">GET</span>
                <h3>/api/seller/inquiries</h3>
                <p>Get seller's inquiries with optional filters</p>
                
                <h4>Query Parameters:</h4>
                <div class="param">
                    <span class="param-name">page</span> 
                    <span class="param-type">(integer, optional)</span> - Page number, defaults to 1
                </div>
                <div class="param">
                    <span class="param-name">limit</span> 
                    <span class="param-type">(integer, optional)</span> - Items per page, defaults to 12
                </div>
                <div class="param">
                    <span class="param-name">status</span> 
                    <span class="param-type">(string, optional)</span> - Filter by status ('pending', 'read', 'answered', 'all'), defaults to 'all'
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "inquiries": [...],
    "pagination": {
        "current_page": 1,
        "total_pages": 2,
        "total_items": 15,
        "per_page": 12
    }
}
                </div>
            </div>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                <h3>/api/seller/inquiries</h3>
                <p>Reply to an inquiry</p>
                
                <h4>Body Parameters:</h4>
                <div class="param">
                    <span class="param-name">inquiry_id</span> 
                    <span class="param-type">(integer, required)</span> - Inquiry ID to reply to
                </div>
                <div class="param">
                    <span class="param-name">message</span> 
                    <span class="param-type">(string, required)</span> - Reply message
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "message": "Reply sent successfully"
}
                </div>
            </div>
        </div>
        
        <div class="endpoint">
            <h3>Admin Features</h3>
            
            <div class="endpoint">
                <span class="method get">GET</span>
                <h3>/api/admin/dashboard</h3>
                <p>Get admin dashboard data including platform stats</p>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "dashboard_data": {
        "stats": {
            "total_users": 150,
            "total_sellers": 25,
            "total_clients": 120,
            "total_products": 500,
            "total_orders": 800,
            "total_revenue": 15000.75,
            "total_inquiries": 320,
            "live_streams_now": 5
        },
        "recent_activities": [...]
    }
}
                </div>
            </div>
            
            <div class="endpoint">
                <span class="method get">GET</span>
                <h3>/api/admin/users</h3>
                <p>Get list of users with optional filters</p>
                
                <h4>Query Parameters:</h4>
                <div class="param">
                    <span class="param-name">page</span> 
                    <span class="param-type">(integer, optional)</span> - Page number, defaults to 1
                </div>
                <div class="param">
                    <span class="param-name">limit</span> 
                    <span class="param-type">(integer, optional)</span> - Items per page, defaults to 12
                </div>
                <div class="param">
                    <span class="param-name">role</span> 
                    <span class="param-type">(string, optional)</span> - Filter by role ('all', 'client', 'seller', 'admin'), defaults to 'all'
                </div>
                <div class="param">
                    <span class="param-name">status</span> 
                    <span class="param-type">(string, optional)</span> - Filter by status ('all', 'active', 'inactive'), defaults to 'all'
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "users": [...],
    "pagination": {
        "current_page": 1,
        "total_pages": 5,
        "total_items": 50,
        "per_page": 12
    }
}
                </div>
            </div>
            
            <div class="endpoint">
                <span class="method put">PUT</span>
                <h3>/api/admin/users</h3>
                <p>Update a user's status</p>
                
                <h4>Body Parameters:</h4>
                <div class="param">
                    <span class="param-name">user_id</span> 
                    <span class="param-type">(integer, required)</span> - User ID to update
                </div>
                <div class="param">
                    <span class="param-name">status</span> 
                    <span class="param-type">(string, required)</span> - New status ('active', 'inactive', 'suspended')
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "message": "User status updated successfully"
}
                </div>
            </div>
            
            <div class="endpoint">
                <span class="method delete">DELETE</span>
                <h3>/api/admin/users</h3>
                <p>Delete a user (excluding admin accounts)</p>
                
                <h4>Query Parameters:</h4>
                <div class="param">
                    <span class="param-name">id</span> 
                    <span class="param-type">(integer, required)</span> - User ID to delete
                </div>
                
                <h4>Response:</h4>
                <div class="example">
{
    "success": true,
    "message": "User deleted successfully"
}
                </div>
            </div>
        </div>
    </div>
</body>
</html>