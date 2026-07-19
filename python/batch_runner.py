# python/batch_runner.py
import logging
import sys
import os
from datetime import datetime

sys.path.insert(0, os.path.dirname(__file__))

from cf.cf_engine import run_cf_engine
from cf.evaluator import run_evaluation

log_dir = os.path.join(os.path.dirname(__file__), '../uploads/logs')
if not os.path.exists(log_dir):
    os.makedirs(log_dir, exist_ok=True)

logging.basicConfig(
    filename=os.path.join(log_dir, 'cf_batch.log'),
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)


def main():
    start = datetime.now()
    logging.info("BATCH CF ENGINE DIMULAI")
    try:
        run_cf_engine()
        results = run_evaluation(k=5)
        logging.info(f"Evaluasi: {results}")
        logging.info("SUKSES")
        print(results)
    except Exception as e:
        logging.error(f"ERROR: {e}", exc_info=True)
        print(f"ERROR: {e}")
    elapsed = (datetime.now() - start).seconds
    logging.info(f"Selesai dalam {elapsed} detik")
    print(f"Selesai dalam {elapsed} detik")


if __name__ == '__main__':
    main()
