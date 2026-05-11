from sentence_transformers import SentenceTransformer

# load ONCE only (important)
model = SentenceTransformer("all-MiniLM-L6-v2", local_files_only=False)

nlp = None  # placeholder if you're not using spaCy yet