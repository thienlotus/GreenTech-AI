FROM python:3.9-slim

WORKDIR /app

# Install system graphic libraries required by OpenCV
RUN apt-get update && apt-get install -y \
    libgl1 \
    libglib2.0-0 \
    libxcb1 \
    && rm -rf /var/lib/apt/lists/*

COPY src/ai_service/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY src/ai_service/ .

EXPOSE 7860

CMD ["python", "api_model.py"]