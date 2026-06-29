from embedding_service import EmbeddingService

class SimilarityEngine:

    def __init__(self):
        self.embedding_service = EmbeddingService()

    def compare_questions(self, question1, question2):

        emb1 = self.embedding_service.generate_embedding(question1)
        emb2 = self.embedding_service.generate_embedding(question2)

        similarity = self.embedding_service.cosine_similarity(
            emb1,
            emb2
        )

        return {
            "similarity_score": round(similarity * 100, 2),
            "is_duplicate": similarity > 0.85
        }