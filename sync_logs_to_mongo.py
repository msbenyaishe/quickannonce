#!/usr/bin/env python3
"""
Synchronize logs.json with MongoDB and export summaries.

The Mongo URI must be provided via the MONGO_URI environment variable.
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

BASE_DIR = Path(_file_).resolve().parent
LOG_FILE = BASE_DIR / "logs.json"
STATS_FILE = BASE_DIR / "stats_actions.csv"
CSV_EXPORT = BASE_DIR / "logs.csv"


def get_mongo_client() -> MongoClient:
    uri = os.getenv("MONGO_URI")
    if not uri:
        raise ValueError("MONGO_URI manquante !")
    return MongoClient(uri)


def load_logs() -> List[Dict[str, Any]]:
    if not LOG_FILE.exists():
        print("Aucun fichier logs.json trouvé.")
        sys.exit(0)

    with LOG_FILE.open("r", encoding="utf-8") as handle:
        data = json.load(handle)

    if not data:
        print("Aucun log à insérer.")
        sys.exit(0)

    if isinstance(data, dict):
        data = [data]

    if not isinstance(data, list):
        raise ValueError("Le fichier logs.json doit contenir un tableau d'objets JSON.")

    return data


def upsert_logs(collection: Collection, logs: List[Dict[str, Any]]) -> int:
    nb_inserts = 0
    for log in logs:
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
    logs = load_logs()
    client = get_mongo_client()
    db = client["stagiaires_admin"]
    col = db["logs"]

    inserted = upsert_logs(col, logs)
    print(f"{inserted} nouveaux logs insérés dans MongoDB Atlas (sans doublons).")

    export_stats(col)
    export_full_collection(col)


if _name_ == "_main_":
    main()