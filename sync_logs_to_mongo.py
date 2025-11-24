#!/usr/bin/env python3
"""
Synchronize logs.json with MongoDB Atlas and export analytics.

Steps:
1. Load logs.json
2. Upsert documents into logs_db.logs (duplicate-safe via MD5 hash)
3. Aggregate action counts into stats_actions.csv
4. Export the full collection to logs.csv for the admin dashboard
"""

import csv
import hashlib
import json
import os
import sys
from pathlib import Path
from typing import Any, Dict, Iterable, List

import pandas as pd
from pymongo import MongoClient
from pymongo.collection import Collection
from pymongo.errors import PyMongoError

BASE_DIR = Path(_file_).resolve().parent
LOG_FILE = BASE_DIR / "logs.json"
STATS_FILE = BASE_DIR / "stats_actions.csv"
CSV_EXPORT = BASE_DIR / "logs.csv"

DEFAULT_URI = (
    "mongodb+srv://quick_user_second:DfhuouESRXAuoJ1d"
    "@cluster0.kr90782.mongodb.net/logs_db?retryWrites=true&w=majority"
)
DB_NAME = "logs_db"
COLLECTION_NAME = "logs"


def get_mongo_client() -> MongoClient:
    mongo_uri = os.getenv("MONGO_URI", DEFAULT_URI)
    try:
        client = MongoClient(mongo_uri, serverSelectionTimeoutMS=15000)
        client.admin.command("ping")
        return client
    except PyMongoError as exc:
        raise ConnectionError(f"Impossible de se connecter à MongoDB: {exc}") from exc


def load_logs() -> List[Dict[str, Any]]:
    if not LOG_FILE.exists():
        raise FileNotFoundError("Aucun fichier logs.json trouvé.")

    with LOG_FILE.open("r", encoding="utf-8") as handle:
        data = json.load(handle)

    if not data:
        raise ValueError("Aucun log à insérer.")

    if isinstance(data, dict):
        data = [data]

    if not isinstance(data, list) or not all(isinstance(item, dict) for item in data):
        raise ValueError("logs.json doit contenir un tableau d'objets JSON.")

    return data


def upsert_logs(collection: Collection, logs: List[Dict[str, Any]]) -> int:
    nb_inserts = 0
    for idx, log in enumerate(logs, start=1):
        raw = json.dumps(log, sort_keys=True, ensure_ascii=False)
        uid = hashlib.md5(raw.encode("utf-8")).hexdigest()
        log_with_id = {**log, "_id": uid}

        result = collection.update_one(
            {"_id": uid},
            {"$setOnInsert": log_with_id},
            upsert=True,
        )
        if result.upserted_id is not None:
            nb_inserts += 1
        if idx % 200 == 0:
            print(f"  Progress: {idx}/{len(logs)} logs traités.")
    return nb_inserts


def export_stats(collection: Collection) -> None:
    pipeline = [
        {"$group": {"_id": "$action", "total": {"$sum": 1}}},
        {"$sort": {"total": -1}},
    ]
    stats = list(collection.aggregate(pipeline))

    if stats:
        df = pd.DataFrame(stats).rename(columns={"_id": "action"})
        df.to_csv(STATS_FILE, index=False)
        print("Rapport d'analyse généré : stats_actions.csv")
    else:
        print("Aucune donnée pour générer le rapport stats_actions.csv.")


def compute_fieldnames(records: Iterable[Dict[str, Any]]) -> List[str]:
    seen: List[str] = []
    for record in records:
        for key in record.keys():
            if key not in seen:
                seen.append(key)
    return seen


def normalize_value(value: Any) -> Any:
    if value is None:
        return ""
    if isinstance(value, (list, dict)):
        return json.dumps(value, ensure_ascii=False)
    return value


def export_full_collection(collection: Collection) -> None:
    docs = list(collection.find())
    if not docs:
        print("Aucun document trouvé dans MongoDB pour logs.csv.")
        return

    headers = compute_fieldnames(docs)
    with CSV_EXPORT.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=headers)
        writer.writeheader()
        for doc in docs:
            row = {field: normalize_value(doc.get(field)) for field in headers}
            writer.writerow(row)
    print("Export complet généré : logs.csv")


def main() -> None:
    try:
        logs = load_logs()
    except Exception as exc:
        print(f"❌ {exc}")
        sys.exit(1)

    try:
        client = get_mongo_client()
        collection = client[DB_NAME][COLLECTION_NAME]

        inserted = upsert_logs(collection, logs)
        print(f"✅ {inserted} nouveaux logs insérés dans MongoDB Atlas (sans doublons).")

        export_stats(collection)
        export_full_collection(collection)
    except Exception as exc:
        print(f"❌ Erreur durant la synchronisation: {exc}")
        sys.exit(1)
    finally:
        try:
            client.close()
        except Exception:
            pass


if _name_ == "_main_":
    main()