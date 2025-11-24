#!/usr/bin/env python3
"""
Sync logs from logs.json into MongoDB and export all MongoDB documents to logs.csv & stats_actions.csv.

Usage:
    python3 sync_logs_to_mongo.py
"""

from __future__ import annotations

import csv
import hashlib
import json
import os
import sys
from pathlib import Path
from typing import Any, Dict, Iterable, List, Optional

from bson import ObjectId
from bson.errors import InvalidId
from pymongo import MongoClient
from pymongo.collection import Collection
from pymongo.errors import PyMongoError, DuplicateKeyError

# --- Config / Paths --------------------------------------------------------
BASE_DIR = Path(__file__).resolve().parent
LOG_FILE = BASE_DIR / "logs.json"
CSV_FILE = BASE_DIR / "logs.csv"
STATS_CSV = BASE_DIR / "stats_actions.csv"

# DEFAULT_URI is only a fallback — do NOT hardcode credentials in repo, store in GitHub secrets
DEFAULT_URI = os.getenv("DEFAULT_MONGO_URI", "")

MONGO_URI = os.getenv("MONGO_URI", DEFAULT_URI)
if not MONGO_URI:
    print("❌ MONGO_URI is not set. Set the MONGO_URI environment variable (use GitHub Secrets).")
    sys.exit(2)

DB_NAME = os.getenv("MONGO_DB") or "logs_db"
COLLECTION_NAME = os.getenv("MONGO_COLLECTION") or "logs"

# --- Helpers ---------------------------------------------------------------
def load_logs(path: Path) -> List[Dict[str, Any]]:
    """Load log entries from logs.json. Returns a list of dicts."""
    if not path.exists():
        # Not fatal; return empty list and let caller handle it
        return []

    try:
        raw = path.read_text(encoding="utf-8").strip()
        if raw == "":
            return []
        payload = json.loads(raw)
    except json.JSONDecodeError as exc:
        raise ValueError(f"logs.json contains invalid JSON: {exc}") from exc

    # Accept a single object or array
    if isinstance(payload, dict):
        payload = [payload]
    if not isinstance(payload, list) or not all(isinstance(item, dict) for item in payload):
        raise ValueError("logs.json must contain an array of JSON objects (or a single object).")
    return payload


def create_client(uri: str) -> MongoClient:
    """Create and validate a MongoDB client."""
    try:
        client = MongoClient(uri, serverSelectionTimeoutMS=15000, connectTimeoutMS=10000, socketTimeoutMS=30000)
        # validate connection
        client.admin.command("ping")
        return client
    except PyMongoError as exc:
        raise ConnectionError(f"Unable to connect to MongoDB: {exc}") from exc


def to_object_id(candidate: Any) -> Any:
    """Convert candidate to ObjectId if it looks like one (supports {"$oid":"..."})."""
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


def compute_content_hash(doc: Dict[str, Any]) -> str:
    """Compute a stable SHA256 hash for a document excluding its _id."""
    d = dict(doc)  # copy
    d.pop("_id", None)
    # use compact separators and sorted keys for deterministic serialization
    j = json.dumps(d, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)
    return hashlib.sha256(j.encode("utf-8")).hexdigest()


def upsert_logs(collection: Collection, logs: List[Dict[str, Any]]) -> int:
    """
    Insert or update log documents in MongoDB, avoiding duplicates using a content_hash.
    Returns number of documents newly inserted (not existing ones).
    """
    inserted_count = 0
    if not logs:
        return 0

    # Ensure an index on content_hash to deduplicate by content
    try:
        collection.create_index([("content_hash", 1)], unique=True, background=True)
    except PyMongoError:
        # index creation failure is non-fatal; operations will still proceed
        pass

    for raw in logs:
        d = dict(raw)  # copy
        content_hash = compute_content_hash(d)
        d["content_hash"] = content_hash

        # If an _id is present, try to replace by _id first
        if "_id" in d:
            d["_id"] = to_object_id(d["_id"])
            try:
                res = collection.replace_one({"_id": d["_id"]}, d, upsert=True)
                if getattr(res, "upserted_id", None) is not None:
                    inserted_count += 1
            except DuplicateKeyError:
                # content_hash unique collision: fall back to upsert by content_hash (merge)
                try:
                    res2 = collection.update_one(
                        {"content_hash": content_hash},
                        {"$set": d},
                        upsert=True,
                    )
                    if getattr(res2, "upserted_id", None) is not None:
                        inserted_count += 1
                except PyMongoError as exc:
                    raise RuntimeError(f"Failed to upsert document (by content_hash) after duplicate-key: {exc}") from exc
            except PyMongoError as exc:
                raise RuntimeError(f"Failed to upsert document with _id={d.get('_id')}: {exc}") from exc
        else:
            # No _id — upsert based on content_hash so identical content is not duplicated
            try:
                res = collection.update_one(
                    {"content_hash": content_hash},
                    {"$setOnInsert": d},
                    upsert=True,
                )
                if getattr(res, "upserted_id", None) is not None:
                    inserted_count += 1
            except DuplicateKeyError:
                # Very rare race — treat as existing
                pass
            except PyMongoError as exc:
                raise RuntimeError(f"Failed to insert document: {exc}") from exc

    return inserted_count


def fetch_documents(collection: Collection) -> List[Dict[str, Any]]:
    """Fetch every document from the MongoDB collection as plain dicts (convert ObjectId to string)."""
    try:
        docs = list(collection.find())
        # Convert ObjectId to string for CSV-friendly output
        for d in docs:
            if "_id" in d and isinstance(d["_id"], ObjectId):
                d["_id"] = str(d["_id"])
        return docs
    except PyMongoError as exc:
        raise RuntimeError(f"Failed to read documents from MongoDB: {exc}") from exc


def compute_fieldnames(records: Iterable[Dict[str, Any]]) -> List[str]:
    """Determine CSV headers by preserving first-seen order."""
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
        # fallback header
        fieldnames = ["_id"]

    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames)
        writer.writeheader()
        for record in records:
            row = {field: normalize_value(record.get(field)) for field in fieldnames}
            writer.writerow(row)


def write_stats_csv(inserted: int, total_docs: int, path: Path) -> None:
    """Write a tiny CSV with sync statistics that the admin page can show."""
    header = ["last_sync_iso", "inserted_count", "total_documents"]
    row = [__import__("datetime").datetime.utcnow().isoformat() + "Z", str(inserted), str(total_docs)]
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.writer(handle)
        writer.writerow(header)
        writer.writerow(row)


# --- Main ------------------------------------------------------------------
def main() -> None:
    print("🔄 Starting logs sync...")
    client: Optional[MongoClient] = None
    try:
        logs = load_logs(LOG_FILE)
        print(f"📄 Found {len(logs)} log entries in {LOG_FILE}")

        client = create_client(MONGO_URI)
        collection = client[DB_NAME][COLLECTION_NAME]

        inserted = 0
        sync_succeeded = False
        if logs:
            inserted = upsert_logs(collection, logs)
            print(f"✅ Inserted/Upserted {inserted} documents into MongoDB collection '{COLLECTION_NAME}'")
            sync_succeeded = True
        else:
            print("ℹ️ No new logs to insert this run.")

        # Only clear the logs.json if DB insert succeeded (or there were no logs)
        # (this keeps remote empty after successful sync)
        if sync_succeeded or not logs:
            try:
                tmp = LOG_FILE.with_suffix(".tmp")
                tmp.write_text("[]", encoding="utf-8")
                tmp.replace(LOG_FILE)
                print(f"🧹 Cleared {LOG_FILE} (wrote empty array).")
            except Exception as exc:
                print(f"⚠ Could not clear logs.json after DB insert: {exc}")

        # Fetch all documents and write CSV
        docs = fetch_documents(collection)
        total_docs = len(docs)
        write_csv(docs, CSV_FILE)
        print(f"📁 Exported {total_docs} documents to {CSV_FILE.resolve()}")

        # Write stats file
        write_stats_csv(inserted, total_docs, STATS_CSV)
        print(f"📊 Wrote sync stats to {STATS_CSV.resolve()}")

    except Exception as exc:
        print(f"❌ Error: {exc}")
        sys.exit(1)
    finally:
        if client is not None:
            client.close()
            print("🔌 MongoDB connection closed.")


if __name__ == "__main__":
    main()