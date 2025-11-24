#!/usr/bin/env python3
"""
Sync logs from logs.json into MongoDB and export to CSV.
Handles the full data cycle:
1. Reads logs from logs.json
2. Inserts new logs into MongoDB
3. Exports all logs to CSV
4. Generates statistics
"""

import os
import json
import sys
import hashlib
import csv
import logging
from pathlib import Path
from datetime import datetime
from pymongo import MongoClient, errors
from bson import ObjectId
from typing import List, Dict, Any, Optional

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger(__name__)

class LogSync:
    def __init__(self):
        # Configuration
        self.mongo_uri = self._get_required_env('MONGO_URI')
        self.db_name = os.getenv('MONGO_DB', 'logsactions')
        self.collection_name = os.getenv('MONGO_COLLECTION', 'actions')
        
        # File paths
        self.base_dir = Path(__file__).resolve().parent
        self.log_file = self.base_dir / 'logs.json'
        self.csv_file = self.base_dir / 'logs.csv'
        self.stats_csv = self.base_dir / 'stats_actions.csv'
        
        # Initialize MongoDB connection
        self.client = None
        self.col = None
        
    def _get_required_env(self, var_name: str) -> str:
        """Get required environment variable or exit if not found."""
        value = os.getenv(var_name)
        if not value:
            logger.error(f"❌ {var_name} environment variable is required!")
            sys.exit(1)
        return value
    
    def connect_mongodb(self) -> None:
        """Establish connection to MongoDB."""
        try:
            self.client = MongoClient(
                self.mongo_uri,
                serverSelectionTimeoutMS=10000,
                connectTimeoutMS=10000,
                socketTimeoutMS=10000
            )
            # Test the connection
            self.client.admin.command('ping')
            self.col = self.client[self.db_name][self.collection_name]
            logger.info(f"✅ Connected to MongoDB: {self.db_name}.{self.collection_name}")
        except errors.ServerSelectionTimeoutError as e:
            logger.error(f"❌ MongoDB connection timeout: {e}")
            raise
        except errors.ConnectionFailure as e:
            logger.error(f"❌ MongoDB connection failed: {e}")
            raise
    
    def read_logs_file(self) -> List[Dict[str, Any]]:
        """Read and parse logs from logs.json."""
        if not self.log_file.exists():
            logger.warning(f"⚠ {self.log_file} not found, creating empty file.")
            self._write_logs_file([])
            return []
        
        try:
            with open(self.log_file, 'r', encoding='utf-8') as f:
                content = f.read().strip()
                if not content:
                    return []
                data = json.loads(content)
                
                # Ensure we always return a list
                if isinstance(data, dict):
                    data = [data]
                elif not isinstance(data, list):
                    logger.error(f"❌ Invalid log format: expected list or dict, got {type(data).__name__}")
                    return []
                    
                logger.info(f"📄 Read {len(data)} log entries from {self.log_file}")
                return data
                
        except json.JSONDecodeError as e:
            logger.error(f"❌ Failed to parse {self.log_file}: {e}")
            return []
        except Exception as e:
            logger.error(f"❌ Error reading {self.log_file}: {e}")
            return []
    
    def _write_logs_file(self, data: List[Dict[str, Any]]) -> bool:
        """Write data to logs.json."""
        try:
            with open(self.log_file, 'w', encoding='utf-8') as f:
                json.dump(data, f, indent=2, ensure_ascii=False)
            return True
        except Exception as e:
            logger.error(f"❌ Failed to write {self.log_file}: {e}")
            return False
    
    def sync_logs_to_mongodb(self, logs: List[Dict[str, Any]]) -> int:
        """Insert logs into MongoDB, skipping duplicates."""
        if not logs:
            logger.info("ℹ️ No logs to process.")
            return 0
            
        inserted_count = 0
        
        for log in logs:
            try:
                # Create a unique ID based on the log content
                log_str = json.dumps(log, sort_keys=True, ensure_ascii=False)
                log_id = hashlib.md5(log_str.encode('utf-8')).hexdigest()
                
                # Add metadata
                log_with_meta = {
                    **log,
                    '_id': log_id,
                    '_imported_at': datetime.utcnow()
                }
                
                # Insert if not exists
                result = self.col.update_one(
                    {'_id': log_id},
                    {'$setOnInsert': log_with_meta},
                    upsert=True
                )
                
                if result.upserted_id is not None:
                    inserted_count += 1
                    
            except Exception as e:
                logger.error(f"⚠️ Failed to insert log: {e}")
                continue
        
        logger.info(f"✅ Inserted {inserted_count} new log entries into MongoDB")
        return inserted_count
    
    def export_to_csv(self) -> None:
        """Export all logs from MongoDB to CSV."""
        try:
            # Get all documents
            cursor = self.col.find({})
            all_docs = list(cursor)
            
            if not all_docs:
                logger.warning("ℹ️ No documents found in the collection")
                self._write_empty_csv()
                return
            
            # Get all unique field names across all documents
            fieldnames = set()
            for doc in all_docs:
                fieldnames.update(doc.keys())
            
            # Ensure _id is always first
            fieldnames = ['_id'] + [f for f in sorted(fieldnames) if f != '_id']
            
            # Write to CSV
            with open(self.csv_file, 'w', encoding='utf-8', newline='') as f:
                writer = csv.DictWriter(f, fieldnames=fieldnames)
                writer.writeheader()
                
                for doc in all_docs:
                    # Convert document to CSV row, handling special types
                    row = {}
                    for field in fieldnames:
                        value = doc.get(field, '')
                        
                        # Handle special types
                        if isinstance(value, (dict, list)):
                            row[field] = json.dumps(value, ensure_ascii=False)
                        elif isinstance(value, ObjectId):
                            row[field] = str(value)
                        elif value is None:
                            row[field] = ''
                        elif isinstance(value, datetime):
                            row[field] = value.isoformat()
                        else:
                            row[field] = value
                    
                    writer.writerow(row)
            
            logger.info(f"✅ Exported {len(all_docs)} documents to {self.csv_file}")
            
        except Exception as e:
            logger.error(f"❌ Failed to export to CSV: {e}")
            self._write_empty_csv()
    
    def _write_empty_csv(self) -> None:
        """Write an empty CSV with just headers."""
        with open(self.csv_file, 'w', encoding='utf-8', newline='') as f:
            writer = csv.writer(f)
            writer.writerow(['_id'])
        logger.info("ℹ️ Created empty logs.csv")
    
    def generate_stats(self) -> None:
        """Generate statistics and save to stats_actions.csv."""
        try:
            pipeline = [
                {"$group": {"_id": "$action", "count": {"$sum": 1}}},
                {"$sort": {"count": -1}}
            ]
            
            stats = list(self.col.aggregate(pipeline))
            
            with open(self.stats_csv, 'w', encoding='utf-8', newline='') as f:
                writer = csv.writer(f)
                writer.writerow(['action', 'count'])
                
                for stat in stats:
                    writer.writerow([stat['_id'], stat['count']])
            
            logger.info(f"📊 Generated statistics for {len(stats)} action types")
            
        except Exception as e:
            logger.error(f"⚠️ Failed to generate statistics: {e}")
            # Create empty stats file
            with open(self.stats_csv, 'w', encoding='utf-8', newline='') as f:
                writer = csv.writer(f)
                writer.writerow(['action', 'count'])
    
    def run(self) -> None:
        """Run the full sync process."""
        start_time = datetime.now()
        logger.info("🚀 Starting log synchronization process")
        
        try:
            # Step 1: Connect to MongoDB
            self.connect_mongodb()
            
            # Step 2: Read logs from file
            logs = self.read_logs_file()
            
            if logs:
                # Step 3: Insert logs into MongoDB
                inserted_count = self.sync_logs_to_mongodb(logs)
                
                # Step 4: Clear the logs file after successful insert
                if inserted_count > 0:
                    self._write_logs_file([])
            
            # Step 5: Export all data to CSV
            self.export_to_csv()
            
            # Step 6: Generate statistics
            self.generate_stats()
            
            # Calculate and log duration
            duration = (datetime.now() - start_time).total_seconds()
            logger.info(f"✨ Sync completed in {duration:.2f} seconds")
            
        except Exception as e:
            logger.error(f"❌ Sync failed: {e}", exc_info=True)
            sys.exit(1)
            
        finally:
            # Always close the MongoDB connection
            if self.client:
                self.client.close()
                logger.info("🔌 MongoDB connection closed")


if __name__ == "__main__":
    sync = LogSync()
    sync.run()