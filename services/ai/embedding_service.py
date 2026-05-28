from sentence_transformers import SentenceTransformer
import numpy as np

class EmbeddingService:

    def __init__(self):
        self.model = SentenceTransformer('all-MiniLM-L6-v2')

    def generate_embedding(self, text):

        if not text:
            return None

        embedding = self.model.encode(text)

        return embedding.tolist()

    def cosine_similarity(self, emb1, emb2):

        emb1 = np.array(emb1)
        emb2 = np.array(emb2)

        similarity = np.dot(emb1, emb2) / (
            np.linalg.norm(emb1) * np.linalg.norm(emb2)
        )

        return float(similarity)