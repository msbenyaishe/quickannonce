from pymongo import MongoClient
from pprint import pprint
import os
import sys
from datetime import datetime

def test_mongodb_connection(connection_string, db_name="logsactions", collection_name="actions"):
    print("🔍 Testing MongoDB Connection...")
    print(f"Connection string: {connection_string[:20]}...")  # Only show first 20 chars for security
    
    try:
        # 1. Test connection
        print("\n1. Testing connection...")
        client = MongoClient(connection_string, serverSelectionTimeoutMS=5000)
        client.admin.command('ping')
        print("✅ Successfully connected to MongoDB server")
        
        # 2. List all databases
        print("\n2. Listing databases...")
        db_list = client.list_database_names()
        print(f"Available databases: {db_list}")
        
        # 3. Access the test database and collection
        db = client[db_name]
        collection = db[collection_name]
        print(f"\n3. Using database: {db_name}")
        print(f"   Collection: {collection_name}")
        
        # 4. Test insert
        print("\n4. Testing insert operation...")
        test_doc = {
            "test": "connection_test",
            "timestamp": datetime.utcnow(),
            "status": "success"
        }
        result = collection.insert_one(test_doc)
        print(f"✅ Inserted test document with ID: {result.inserted_id}")
        
        # 5. Test find
        print("\n5. Testing find operation...")
        found = collection.find_one({"_id": result.inserted_id})
        print("Found document:")
        pprint(found)
        
        # 6. Clean up
        print("\n6. Cleaning up test data...")
        collection.delete_one({"_id": result.inserted_id})
        print("✅ Test document removed")
        
        # 7. Count documents
        count = collection.count_documents({})
        print(f"\n7. Total documents in collection: {count}")
        
        if count > 0:
            print("\nSample document from collection:")
            pprint(collection.find_one())
            
    except Exception as e:
        print(f"\n❌ Error: {str(e)}", file=sys.stderr)
    finally:
        if 'client' in locals():
            client.close()
            print("\n🔌 MongoDB connection closed")

if __name__ == "__main__":
    # Get connection string from environment or prompt
    mongo_uri = os.getenv("MONGO_URI")
    if not mongo_uri:
        mongo_uri = input("Enter your MongoDB connection string: ").strip()
    
    # Get database and collection names (optional)
    db_name = input(f"Database name [logsactions]: ").strip() or "logsactions"
    collection_name = input(f"Collection name [actions]: ").strip() or "actions"
    
    test_mongodb_connection(mongo_uri, db_name, collection_name)