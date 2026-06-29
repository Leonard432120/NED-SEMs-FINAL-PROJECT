#!/usr/bin/env python
# -*- coding: utf-8 -*-
import sys, io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')
"""
NED-SEMs Question AI -- Model Trainer
======================================
Run this ONCE to download and save the model locally.
After this, the system works fully OFFLINE.

Usage:
    python train_model.py

Output:
    model_cache/minilm_local/   <- saved transformer model
    model_cache/bloom_embs.npy  <- pre-computed Bloom taxonomy embeddings
    model_cache/bloom_labels.json
    model_cache/training_info.json
"""

import os
import sys
import json
import time
import warnings

warnings.filterwarnings('ignore')
os.environ['TOKENIZERS_PARALLELISM'] = 'false'
os.environ['HF_HUB_DISABLE_PROGRESS_BARS'] = '1'

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
CACHE_DIR = os.path.join(BASE_DIR, 'model_cache')
MODEL_LOCAL = os.path.join(CACHE_DIR, 'minilm_local')
BLOOM_EMBS = os.path.join(CACHE_DIR, 'bloom_embs.npy')
BLOOM_LABELS = os.path.join(CACHE_DIR, 'bloom_labels.json')
TRAINING_INFO = os.path.join(CACHE_DIR, 'training_info.json')

# ------------------------------------------------------------
# Rich Bloom Taxonomy Training Examples
# More examples per level = better embedding alignment
# ------------------------------------------------------------
BLOOM_TRAINING_DATA = {
    "Remember": [
        "Define the term photosynthesis.",
        "List the names of the planets in the solar system.",
        "State the law of conservation of energy.",
        "Identify the parts of a cell.",
        "Name the components of the human digestive system.",
        "Recall the date of independence.",
        "What is the definition of osmosis?",
        "Write down the formula for calculating speed.",
        "Who invented the telephone?",
        "When did World War II end?",
        "What are the types of rocks?",
        "State Newton's first law of motion.",
        "List three properties of metals.",
        "Name the capital city of Malawi.",
        "What is the atomic number of carbon?",
    ],
    "Understand": [
        "Explain how photosynthesis works in plants.",
        "Describe the water cycle and its stages.",
        "Summarize the main causes of climate change.",
        "Interpret the meaning of this graph.",
        "Classify the following organisms into groups.",
        "Give an example of a chemical reaction.",
        "What does the term 'democracy' mean?",
        "Outline the steps in the scientific method.",
        "Explain why the sky appears blue.",
        "Describe the process of cell division.",
        "How does the heart pump blood through the body?",
        "Distinguish between mass and weight.",
        "Paraphrase the following passage in your own words.",
        "What is meant by sustainable development?",
        "Explain the difference between acids and bases.",
    ],
    "Apply": [
        "Solve the following quadratic equation.",
        "Calculate the speed of an object given distance and time.",
        "Demonstrate how to use a compass to find North.",
        "Use the formula to find the area of the triangle.",
        "Show how this concept applies to real life.",
        "Apply the principle of buoyancy to this problem.",
        "Compute the interest on a loan of MK 50,000 at 12% for 3 years.",
        "Construct a diagram showing the food web.",
        "Use Ohm's law to calculate the current in the circuit.",
        "Determine the pH of the given solution.",
        "Predict what will happen when the temperature increases.",
        "Draw a graph of the results obtained in the experiment.",
        "Prepare a budget for a school project using the given data.",
        "How would you apply this formula to solve the problem?",
        "Find the value of x in the equation 3x + 7 = 22.",
    ],
    "Analyze": [
        "Compare the two approaches to solving this problem.",
        "Differentiate between plant cells and animal cells.",
        "Analyze the relationship between temperature and solubility.",
        "Break down the components of this ecosystem.",
        "Examine the causes of the economic recession.",
        "What are the differences between ionic and covalent bonds?",
        "Investigate the factors affecting the rate of a chemical reaction.",
        "Distinguish between the causes and effects of deforestation.",
        "Why do some materials conduct electricity while others do not?",
        "Analyze the trend shown in the data table.",
        "Compare and contrast the two historical events.",
        "What evidence supports or contradicts the hypothesis?",
        "Break down the steps in the process and identify weaknesses.",
        "How does changing one variable affect the other in this experiment?",
        "Examine the structural differences between the two compounds.",
    ],
    "Evaluate": [
        "Justify your answer with evidence from the text.",
        "Evaluate the effectiveness of the government's economic policy.",
        "Critique the experimental design and identify its limitations.",
        "Assess the impact of human activity on biodiversity.",
        "Judge which of the two methods is more efficient and why.",
        "Defend your position on whether renewable energy should be prioritized.",
        "What is the best solution to this environmental problem and why?",
        "Give reasons for your decision about which material is most suitable.",
        "Is the conclusion drawn from the data valid? Explain your reasoning.",
        "To what extent do you agree with the statement? Justify your answer.",
        "Rate the quality of the experiment and suggest improvements.",
        "Which approach would you recommend to the school board? Why?",
        "Based on the evidence, is the hypothesis supported or rejected?",
        "Evaluate the advantages and disadvantages of using solar energy.",
        "Should the government ban single-use plastics? Defend your view.",
    ],
    "Create": [
        "Design a solution to reduce plastic pollution in your community.",
        "Develop a plan to improve water access in rural areas.",
        "Construct a model to demonstrate how an electric circuit works.",
        "Create an original story that illustrates the theme of perseverance.",
        "Propose a strategy for increasing food production in Malawi.",
        "Formulate a hypothesis about the effect of light on plant growth.",
        "Invent a new method for purifying water in remote villages.",
        "Produce a report on the impact of deforestation in your region.",
        "Compose a short essay arguing for environmental conservation.",
        "Design an experiment to test the effect of fertilizer on crop yield.",
        "Write a program that calculates the average score of students.",
        "Create a poster campaign to promote hygiene in your school.",
        "Devise a new approach to teaching mathematics to struggling learners.",
        "Construct a logical argument for why the legal age should be changed.",
        "Build a prototype of a solar-powered device using available materials.",
    ]
}


def print_step(msg):
    print(f"\n{'='*60}")
    print(f"  {msg}")
    print(f"{'='*60}")


def main():
    print("\n" + "="*60)
    print("  NED-SEMs AI Model Trainer")
    print("  This runs ONCE. After this, no internet needed.")
    print("="*60)

    os.makedirs(CACHE_DIR, exist_ok=True)

    # ---------------------------------------------------------
    # Step 1: Load SentenceTransformer and save locally
    # ---------------------------------------------------------
    print_step("Step 1/3: Loading sentence-transformers model...")
    t0 = time.time()

    from sentence_transformers import SentenceTransformer
    import numpy as np

    if os.path.exists(MODEL_LOCAL) and os.listdir(MODEL_LOCAL):
        print(f"  [OK] Local model already exists at: {MODEL_LOCAL}")
        print(f"  -&gt; Loading from local cache...")
        model = SentenceTransformer(MODEL_LOCAL)
    else:
        print(f"  -&gt; Downloading 'all-MiniLM-L6-v2' from HuggingFace...")
        print(f"  -&gt; This only happens ONCE.")
        model = SentenceTransformer('all-MiniLM-L6-v2')
        print(f"  -&gt; Saving model to: {MODEL_LOCAL}")
        model.save(MODEL_LOCAL)
        print(f"  [OK] Model saved locally.")

    elapsed = round(time.time() - t0, 1)
    print(f"  [OK] Model ready in {elapsed}s")

    # ---------------------------------------------------------
    # Step 2: Pre-compute Bloom taxonomy embeddings
    # ---------------------------------------------------------
    print_step("Step 2/3: Training Bloom taxonomy embeddings...")
    t0 = time.time()

    bloom_embeddings = {}
    bloom_labels_list = list(BLOOM_TRAINING_DATA.keys())

    for level, examples in BLOOM_TRAINING_DATA.items():
        print(f"  -&gt; Encoding {len(examples)} examples for '{level}'...", end='', flush=True)
        embeddings = model.encode(examples, batch_size=16, show_progress_bar=False)
        # Use mean pooling across all examples for a robust level embedding
        level_embedding = np.mean(embeddings, axis=0)
        bloom_embeddings[level] = level_embedding
        print(f" [OK]")

    # Stack into array for fast cosine similarity
    emb_matrix = np.array([bloom_embeddings[lbl] for lbl in bloom_labels_list])
    np.save(BLOOM_EMBS, emb_matrix)

    with open(BLOOM_LABELS, 'w') as f:
        json.dump(bloom_labels_list, f)

    elapsed = round(time.time() - t0, 1)
    print(f"\n  [OK] Bloom embeddings trained and saved in {elapsed}s")
    print(f"  [OK] Saved to: {BLOOM_EMBS}")
    print(f"  [OK] Labels:   {BLOOM_LABELS}")

    # Quick validation
    print("\n  -&gt; Running quick validation...")
    test_questions = [
        ("State Newton's first law.", "Remember"),
        ("Explain how photosynthesis works.", "Understand"),
        ("Solve the equation x + 5 = 12.", "Apply"),
        ("Compare plant and animal cells.", "Analyze"),
        ("Evaluate the effectiveness of the method.", "Evaluate"),
        ("Design a water purification system.", "Create"),
    ]

    correct = 0
    emb_matrix_norm = emb_matrix / (np.linalg.norm(emb_matrix, axis=1, keepdims=True) + 1e-9)

    for question, expected in test_questions:
        q_emb = model.encode([question])[0]
        q_emb_norm = q_emb / (np.linalg.norm(q_emb) + 1e-9)
        scores = emb_matrix_norm @ q_emb_norm
        predicted = bloom_labels_list[int(np.argmax(scores))]
        match = "[PASS]" if predicted == expected else "[FAIL]"
        if predicted == expected:
            correct += 1
        print(f"    {match} '{question[:45]}' -> {predicted} (expected {expected})")

    accuracy = round(correct / len(test_questions) * 100)
    print(f"\n  [OK] Bloom Validation Accuracy: {accuracy}% ({correct}/{len(test_questions)})")

    # ---------------------------------------------------------
    # Step 3: Save training metadata
    # ---------------------------------------------------------
    print_step("Step 3/3: Saving training metadata...")

    info = {
        "trained_at": time.strftime('%Y-%m-%d %H:%M:%S'),
        "model_name": "all-MiniLM-L6-v2",
        "model_local_path": MODEL_LOCAL,
        "bloom_levels": bloom_labels_list,
        "bloom_examples_per_level": {k: len(v) for k, v in BLOOM_TRAINING_DATA.items()},
        "validation_accuracy_pct": accuracy,
        "python_version": sys.version,
        "status": "ready"
    }

    with open(TRAINING_INFO, 'w') as f:
        json.dump(info, f, indent=2)

    print(f"  [OK] Training info saved to: {TRAINING_INFO}")

    print("\n" + "="*60)
    print("  TRAINING COMPLETE!")
    print(f"  Bloom Accuracy: {accuracy}%")
    print(f"  Model cache:    {CACHE_DIR}")
    print("  The AI will now work OFFLINE — no internet needed.")
    print("="*60 + "\n")


if __name__ == '__main__':
    main()
