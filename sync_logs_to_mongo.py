#!/usr/bin/env python3
"""
Sync logs from logs.json into MongoDB and export all MongoDB documents to logs.csv.

Usage:
    python3 sync_logs_to_mongo.py
"""

import csv
import json
import os
import sys
from pathlib import Path
from typing import Any, Dict, Iterable, List, Optional

from bson import ObjectId
from bson.errors import InvalidId
from pymongo import MongoClient
from pymongo.collection import Collection
from pymongo.errors import PyMongoError

BASE_DIR = Path(_file_).resolve().parent
LOG_FILE = BASE_DIR / "logs.json"
CSV_FILE = BASE_DIR / "logs.csv"
DEFAULT_URI = "mongodb+srv://quick_user_second:DfhuouESRXAuoJ1d@cluster0.kr90782.mongodb.net/"

MONGO_URI = os.getenv("MONGO_URI", DEFAULT_URI)
DB_NAME = "logs_db"
COLLECTION_NAME = "logs"


def load_logs(path: Path) -> List[Dict[str, Any]]:
    """Load log entries from logs.json."""
    if not path.exists():
        raise FileNotFoundError("logs.json not found in the current directory.")

    try:
        with path.open("r", encoding="utf-8") as handle:
            payload = json.load(handle)
    except json.JSONDecodeError as exc:
        raise ValueError(f"logs.json contains invalid JSON: {exc}") from exc

    if isinstance(payload, dict):
        payload = [payload]

    if isinstance(payload, dict):
        payload = [payload]

    if not isinstance(payload, list) or not all(isinstance(item, dict) for item in payload):
        raise ValueError("logs.json must contain an array of JSON objects.")

    return payload


def create_client(uri: str) -> MongoClient:
    """Create and validate a MongoDB client."""
    try:
        client = MongoClient(
            uri,
            serverSelectionTimeoutMS=15000,
            connectTimeoutMS=10000,
            socketTimeoutMS=30000,
        )
        client.admin.command("ping")
        return client
    except PyMongoError as exc:
        raise ConnectionError(f"Unable to connect to MongoDB: {exc}") from exc


def to_object_id(candidate: Any) -> Any:
    """Convert string/dict representations of ObjectId to real ObjectId when possible."""
    if isinstance(candidate, ObjectId):
        return candidate
    if isinstance(candidate, dict) and "$oid" in candidate:
        candidate = candidate.get("$oid")
    if isinstance(candidate, str):
        try:
            return ObjectId(candidate)
        except InvalidId:
            return candidate
    return candidate


def upsert_logs(collection: Collection, logs: List[Dict[str, Any]]) -> int:
    """Insert or update log documents in MongoDB."""
    inserted = 0
    for idx, log in enumerate(logs, start=1):
        doc = dict(log)
        if "_id" in doc:
            doc["_id"] = to_object_id(doc["_id"])
        else:
            doc["_id"] = ObjectId()

        try:
            result = collection.replace_one({"_id": doc["_id"]}, doc, upsert=True)
            if result.upserted_id is not None:
                inserted += 1
        except PyMongoError as exc:
            raise RuntimeError(f"Failed to upsert log #{idx}: {exc}") from exc

    return inserted


def fetch_documents(collection: Collection) -> List[Dict[str, Any]]:
    """Fetch every document from the MongoDB collection."""
    try:
        return list(collection.find())
    except PyMongoError as exc:
        raise RuntimeError(f"Failed to read documents from MongoDB: {exc}") from exc


def compute_fieldnames(records: Iterable[Dict[str, Any]]) -> List[str]:
    """Determine CSV headers by preserving their first-seen order."""
    seen: List[str] = []
    for record in records:
        for key in record.keys():
            if key not in seen:
                seen.append(key)
    return seen


def normalize_value(value: Any) -> Any:
    """Convert complex values into CSV-friendly strings."""
    if value is None:
        return ""
    if isinstance(value, (list, dict)):
        return json.dumps(value, ensure_ascii=False)
    if isinstance(value, (str, int, float, bool)):
        return value
    return str(value)


def write_csv(records: List[Dict[str, Any]], path: Path) -> None:
    """Write MongoDB documents to logs.csv with UTF-8 encoding."""
    fieldnames = compute_fieldnames(records)
    if not fieldnames:
        # Fallback to a minimal header so the CSV is still valid.
        fieldnames = ["_id"]

    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames)
        writer.writeheader()
        for record in records:
            row = {field: normalize_value(record.get(field)) for field in fieldnames}
            writer.writerow(row)


def main() -> None:
    print("🔄 Starting log sync...")
    client: Optional[MongoClient] = None

    try:
        logs = load_logs(LOG_FILE)
        if logs:
            print(f"📄 Loaded {len(logs)} log entries from logs.json")
        else:
            print("ℹ logs.json is empty. Skipping MongoDB inserts.")

        client = create_client(MONGO_URI)
        collection = client[DB_NAME][COLLECTION_NAME]
        if logs:
            inserted = upsert_logs(collection, logs)
            print(f"✅ Inserted/updated {inserted} documents in MongoDB collection '{COLLECTION_NAME}'")

        documents = fetch_documents(collection)
        print(f"📥 Retrieved {len(documents)} documents from MongoDB")

        write_csv(documents, CSV_FILE)
        print(f"📁 Exported data to {CSV_FILE.resolve()}")
    except Exception as exc:
        print(f"❌ {exc}")
        sys.exit(1)
    finally:
        if client is not None:
            client.close()
            print("🔌 MongoDB connection closed.")


if _name_ == "_main_":
    main()