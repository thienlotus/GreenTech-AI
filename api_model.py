import os
os.environ.setdefault('YOLO_AUTOINSTALL', 'False')
os.environ.setdefault('YOLO_CONFIG_DIR', '/tmp/Ultralytics')
os.environ.setdefault('OMP_NUM_THREADS', '1')
os.environ.setdefault('MKL_NUM_THREADS', '1')

from flask import Flask, request, jsonify
from flask_cors import CORS  # Thư viện cho phép gọi API khác miền
from ultralytics import YOLO
import time
import math
import uuid
from werkzeug.utils import secure_filename
import cv2 
import base64 # Thư viện mã hóa ảnh
import numpy as np

app = Flask(__name__)
CORS(app) # Kích hoạt CORS

# Cấu hình tối ưu Medium (Ngyên chón)
MODEL_CONFIDENCE_THRESHOLD = 0.25
DEFAULT_CLASS_THRESHOLD = 0.30
DETECT_INFERENCE_IMAGE_SIZE = 576  # Phải chia hết cho 32 (YOLO stride), tối ưu từ 640
MAX_DETECT_IMAGE_EDGE = 1280

# Tối ưu Live Realtime (Medium với 576px - bằng DETECT, chia hết cho 32)
LIVE_CONFIDENCE_THRESHOLD = 0.28  # Công thống với detect
LIVE_INFERENCE_IMAGE_SIZE = 576  # 560→576 (chia hết 32, YOLO tối ưu khỏi warning)
MAX_LIVE_FRAME_EDGE = 1280  # Tăng 640 → 1280 (giảm lossy preprocessing)

SINGLE_DETECTION_STRICT_THRESHOLD = 0.72
SINGLE_DETECTION_MIN_AREA_RATIO = 0.018
MIN_BOX_AREA_RATIO = 0.0004
MAX_STORED_FILES_PER_DIR = 400
MAX_FILE_AGE_SECONDS = 60 * 60 * 24 * 3
ALLOWED_EXTENSIONS = {'.jpg', '.jpeg', '.png', '.webp'}
CLASS_NAMES = ['aphids', 'bocanhcung', 'chauchau', 'ocsen', 'sauhai']
STRICT_SINGLE_DETECTION_CLASSES = {3}  # Chỉ siết mạnh với class dễ nhầm (ocsen)
LIVE_SESSION_TTL_SECONDS = 60 * 15
LIVE_TRACK_MAX_GAP_SECONDS = 3.0
LIVE_TRACK_STALE_SECONDS = 8.0
LIVE_MAX_DIRECTION_EVENTS_PER_TRACK = 10
LIVE_MAX_RESPONSE_TRACKS = 15
LIVE_MATCH_DIAG_FACTOR = 0.06
LIVE_MIN_MATCH_DISTANCE = 36.0
LIVE_MAX_MATCH_DISTANCE = 140.0
LIVE_HIGH_SPEED_PX_PER_S = 45.0
LIVE_DIRECTION_MIN_DISTANCE_PX = 24.0
LIVE_WARNING_COOLDOWN_SECONDS = 8.0

# ============ ALERT CONFIGURATION (TUI CHINH) ============
# Muc canh bao - tuong tu camera giao thong (Xanh/Vang/Cam/Do)
ALERT_THRESHOLDS = {
    'green': {'total_visible': 0, 'moving_count': 0, 'avg_speed': 0, 'impact_score': 0},
    'yellow': {'total_visible': 3, 'moving_count': 1, 'avg_speed': 12, 'impact_score': 25},   # Canh bao nhe
    'orange': {'total_visible': 5, 'moving_count': 2, 'avg_speed': 25, 'impact_score': 50},   # Canh bao trung
    'red': {'total_visible': 8, 'moving_count': 4, 'avg_speed': 40, 'impact_score': 70},      # Canh bao do - NGUY HIEM
}

# Alert message tuong ung
ALERT_MESSAGES = {
    'green': 'Binh thuong - Khong phat hien dich hai',
    'yellow': 'Canh bao nhe - Phat hien dich hai (+3 ca the)',
    'orange': 'Canh bao trung - Muc do cao (+5 ca the, lan rong)',
    'red': 'NGUY HIEM - Muc do ruat (8+ ca the, lan rong nhanh)',
}

CLASS_VI_NAMES = {
    'aphids': 'Rệp mềm',
    'bocanhcung': 'Bọ cánh cứng',
    'chauchau': 'Châu chấu',
    'ocsen': 'Ốc sên',
    'sauhai': 'Sâu hại'
}

CLASS_IMPACT_WEIGHT = {
    'aphids': 1.0,
    'bocanhcung': 1.1,
    'chauchau': 1.2,
    'ocsen': 1.35,
    'sauhai': 1.25
}

CLASS_CONFIDENCE_THRESHOLDS = {
    0: 0.30,  # aphids 
    1: 0.25,  # bocanhcung
    2: 0.25,  # chauchau
    3: 0.25,  # ocsen
    4: 0.25,  # sauhai
}

# Confidence threshold của Live (cân bằng: tương tự detect)
LIVE_CLASS_CONFIDENCE_THRESHOLDS = {
    0: 0.30,  # aphids 
    1: 0.25,  # bocanhcung
    2: 0.25,  # chauchau
    3: 0.25,  # ocsen
    4: 0.25,  # sauhai
}

# Sửa lại đường dẫn an toàn cho Docker
RESULT_FOLDER = 'results'
os.makedirs(RESULT_FOLDER, exist_ok=True)

try:
    cv2.setNumThreads(1)
except Exception:
    pass

print("\n[*] Dang tai mo hinh AI (YOLOv11 Medium Optimized)...\n")
model = None

try:
    print("  • Load best.pt (Medium)...")
    model = YOLO('best.pt')
    print("    ✓ Model loaded successfully")
except Exception as e:
    print(f"    ✗ ERROR: {e}")
    import sys
    sys.exit(1)

# Warmup model (toi uu inference sau nay)
print("\n[*] Warmup model (toi uu inference)...")
try:
    # Warmup 1: Live size (576px - cùng detect)
    print(f"  1. Warmup {LIVE_INFERENCE_IMAGE_SIZE}px (live size)...")
    warmup_live = np.zeros((LIVE_INFERENCE_IMAGE_SIZE, LIVE_INFERENCE_IMAGE_SIZE, 3), dtype=np.uint8)
    model(warmup_live, conf=LIVE_CONFIDENCE_THRESHOLD, imgsz=LIVE_INFERENCE_IMAGE_SIZE, verbose=False)
    
    # Warmup 2: Detect size (576px)
    print(f"  2. Warmup {DETECT_INFERENCE_IMAGE_SIZE}px (detect size)...")
    warmup_detect = np.zeros((DETECT_INFERENCE_IMAGE_SIZE, DETECT_INFERENCE_IMAGE_SIZE, 3), dtype=np.uint8)
    model(warmup_detect, conf=MODEL_CONFIDENCE_THRESHOLD, imgsz=DETECT_INFERENCE_IMAGE_SIZE, verbose=False)
    
    print("\n✓ Model ready - Inference optimized\n")
except Exception as warmup_error:
    print(f"\n[WARNING] Warmup that bai (van hoat dong): {warmup_error}\n")

# BỘ TỪ ĐIỂN CLASS CHUẨN (đồng bộ với best.pt mới)
correct_names = {idx: name for idx, name in enumerate(CLASS_NAMES)}

ALLOWED_CLASS_IDS = set(range(len(CLASS_NAMES)))
live_sessions = {}


def get_threshold_for_class(class_id: int) -> float:
    return CLASS_CONFIDENCE_THRESHOLDS.get(class_id, DEFAULT_CLASS_THRESHOLD)


def get_live_threshold_for_class(class_id: int) -> float:
    """Lấy confidence threshold của Live (strict hơn)"""
    return LIVE_CLASS_CONFIDENCE_THRESHOLDS.get(class_id, LIVE_CONFIDENCE_THRESHOLD)


def get_class_name_vi(class_name: str) -> str:
    return CLASS_VI_NAMES.get(class_name, class_name)


def draw_detections(image, detections):
    output = image.copy()
    for item in detections:
        x1, y1, x2, y2 = item['bbox']
        label = f"{item['class_name']} {item['confidence']:.2f}"
        color = (25, 196, 126)

        cv2.rectangle(output, (x1, y1), (x2, y2), color, 2)

        (text_w, text_h), _ = cv2.getTextSize(label, cv2.FONT_HERSHEY_SIMPLEX, 0.55, 2)
        text_y = y1 - 8 if y1 - 8 > text_h else y1 + text_h + 6
        cv2.rectangle(output, (x1, text_y - text_h - 6), (x1 + text_w + 8, text_y + 2), color, -1)
        cv2.putText(output, label, (x1 + 4, text_y - 3), cv2.FONT_HERSHEY_SIMPLEX, 0.55, (0, 0, 0), 2, cv2.LINE_AA)

    return output


def prune_folder(folder_path: str, max_files: int, max_age_seconds: int) -> None:
    try:
        file_entries = []
        now = time.time()
        for name in os.listdir(folder_path):
            full_path = os.path.join(folder_path, name)
            if not os.path.isfile(full_path):
                continue
            try:
                mtime = os.path.getmtime(full_path)
                file_entries.append((full_path, mtime))
            except OSError:
                continue

        # Xóa file quá hạn trước.
        for full_path, mtime in file_entries:
            if (now - mtime) > max_age_seconds:
                try:
                    os.remove(full_path)
                except OSError:
                    pass

        # Nếu vẫn còn nhiều file, xóa tiếp từ cũ nhất.
        file_entries = []
        for name in os.listdir(folder_path):
            full_path = os.path.join(folder_path, name)
            if os.path.isfile(full_path):
                try:
                    file_entries.append((full_path, os.path.getmtime(full_path)))
                except OSError:
                    continue

        if len(file_entries) > max_files:
            file_entries.sort(key=lambda item: item[1])
            excess_count = len(file_entries) - max_files
            for full_path, _ in file_entries[:excess_count]:
                try:
                    os.remove(full_path)
                except OSError:
                    pass
    except OSError:
        pass


def prune_live_sessions() -> None:
    now = time.time()
    stale_keys = [
        key for key, session in live_sessions.items()
        if (now - float(session.get('last_seen', now))) > LIVE_SESSION_TTL_SECONDS
    ]
    for key in stale_keys:
        live_sessions.pop(key, None)


def decode_frame_data(frame_data: str):
    if not isinstance(frame_data, str):
        return None

    raw = frame_data.strip()
    if raw == '':
        return None

    if raw.startswith('data:image'):
        pieces = raw.split(',', 1)
        if len(pieces) != 2:
            return None
        raw = pieces[1]

    try:
        binary = base64.b64decode(raw, validate=True)
    except Exception:
        return None

    if not binary:
        return None

    np_buf = np.frombuffer(binary, dtype=np.uint8)
    if np_buf.size == 0:
        return None

    return cv2.imdecode(np_buf, cv2.IMREAD_COLOR)


def decode_uploaded_image(file_storage):
    if file_storage is None:
        return None

    try:
        binary = file_storage.read()
    except Exception:
        return None

    if not binary:
        return None

    np_buf = np.frombuffer(binary, dtype=np.uint8)
    if np_buf.size == 0:
        return None

    return cv2.imdecode(np_buf, cv2.IMREAD_COLOR)


def resize_for_inference(image, max_edge: int):
    if image is None:
        return None

    try:
        img_h, img_w = image.shape[:2]
    except Exception:
        return image

    longest_edge = max(int(img_h), int(img_w))
    if longest_edge <= 0 or longest_edge <= max_edge:
        return image

    scale = float(max_edge) / float(longest_edge)
    new_w = max(1, int(round(img_w * scale)))
    new_h = max(1, int(round(img_h * scale)))
    interpolation = cv2.INTER_AREA if scale < 1.0 else cv2.INTER_LINEAR
    return cv2.resize(image, (new_w, new_h), interpolation=interpolation)


def vector_direction_label(dx: float, dy: float, min_distance: float = LIVE_DIRECTION_MIN_DISTANCE_PX) -> str:
    distance = math.hypot(dx, dy)
    if distance < min_distance:
        return 'Đứng yên'

    angle = (math.degrees(math.atan2(dy, dx)) + 360.0) % 360.0
    if angle >= 337.5 or angle < 22.5:
        return 'Sang phải'
    if angle < 67.5:
        return 'Xuống phải'
    if angle < 112.5:
        return 'Đi xuống'
    if angle < 157.5:
        return 'Xuống trái'
    if angle < 202.5:
        return 'Sang trái'
    if angle < 247.5:
        return 'Lên trái'
    if angle < 292.5:
        return 'Đi lên'
    return 'Lên phải'


def get_live_session(session_id: str):
    session = live_sessions.get(session_id)
    if session is None:
        session = {
            'created_at': time.time(),
            'last_seen': time.time(),
            'next_track_id': 1,
            'tracks': {},
            'last_warning_signature': '',
            'last_warning_at': 0.0,
        }
        live_sessions[session_id] = session

    session['last_seen'] = time.time()
    return session


def match_detections_to_tracks(detections, tracks, frame_w: int, frame_h: int, timestamp_seconds: float):
    if not detections or not tracks:
        return {}

    frame_diag = math.hypot(frame_w, frame_h)
    max_distance = min(max(frame_diag * LIVE_MATCH_DIAG_FACTOR, LIVE_MIN_MATCH_DISTANCE), LIVE_MAX_MATCH_DISTANCE)

    candidate_pairs = []
    for det_index, det in enumerate(detections):
        for track_id, track in tracks.items():
            if int(track['class_id']) != int(det['class_id']):
                continue

            if (timestamp_seconds - float(track['last_seen_at'])) > LIVE_TRACK_MAX_GAP_SECONDS:
                continue

            prev_x, prev_y = track['last_centroid']
            distance = math.hypot(float(det['cx']) - float(prev_x), float(det['cy']) - float(prev_y))
            if distance <= max_distance:
                candidate_pairs.append((distance, det_index, track_id))

    candidate_pairs.sort(key=lambda item: item[0])

    matched = {}
    used_detections = set()
    used_tracks = set()

    for distance, det_index, track_id in candidate_pairs:
        if det_index in used_detections or track_id in used_tracks:
            continue
        matched[det_index] = track_id
        used_detections.add(det_index)
        used_tracks.add(track_id)

    return matched


def movement_level_from_speed(speed_px_per_s: float, transition_count: int) -> str:
    if transition_count >= 2 or speed_px_per_s >= LIVE_HIGH_SPEED_PX_PER_S:
        return 'Di chuyển mạnh'
    if speed_px_per_s >= 15.0 or transition_count == 1:
        return 'Di chuyển vừa'
    if speed_px_per_s >= 3.0:
        return 'Di chuyển nhẹ'
    return 'Đứng yên'


def compute_impact_summary(species_counts, tracks, spread_events, total_visible: int):
    species_pressure = 0.0
    for class_name, qty in species_counts.items():
        species_pressure += CLASS_IMPACT_WEIGHT.get(class_name, 1.0) * float(qty)

    moving_fast_count = 0
    speed_sum = 0.0
    moving_track_count = 0
    aggregate_dx = 0.0
    aggregate_dy = 0.0

    for track in tracks:
        speed = float(track.get('last_speed_px_s', 0.0))
        speed_sum += speed
        net_dx = float(track.get('net_dx', 0.0))
        net_dy = float(track.get('net_dy', 0.0))
        net_distance = math.hypot(net_dx, net_dy)

        if speed >= 3.0 or net_distance >= LIVE_DIRECTION_MIN_DISTANCE_PX:
            moving_track_count += 1
        if speed >= LIVE_HIGH_SPEED_PX_PER_S:
            moving_fast_count += 1

        if net_distance >= LIVE_DIRECTION_MIN_DISTANCE_PX:
            aggregate_dx += net_dx
            aggregate_dy += net_dy

    avg_speed = speed_sum / max(len(tracks), 1)
    spread_event_pressure = len(spread_events)

    dominant_direction = vector_direction_label(aggregate_dx, aggregate_dy, LIVE_DIRECTION_MIN_DISTANCE_PX * 0.75)
    if dominant_direction == 'Đứng yên':
        dominant_direction = 'Không rõ'

    if moving_track_count == 0:
        spread_level = 'Ổn định'
    elif moving_track_count >= max(3, int(math.ceil(max(total_visible, 1) * 0.6))) and avg_speed >= 16.0:
        spread_level = 'Lây lan nhanh'
    elif moving_track_count >= 2:
        spread_level = 'Đang lan rộng'
    else:
        spread_level = 'Di chuyển cục bộ'

    impact_score = int(min(
        100,
        species_pressure * 12.0 +
        spread_event_pressure * 9.0 +
        moving_fast_count * 16.0 +
        moving_track_count * 8.0 +
        min(avg_speed, 55.0) * 0.8 +
        min(float(total_visible), 12.0) * 4.5
    ))

    if impact_score >= 70:
        impact_level = 'Nghiêm trọng'
        risk_level = 'Cao'
        alert_level = 'red'
    elif impact_score >= 50:
        impact_level = 'Trung bình'
        risk_level = 'Trung bình'
        alert_level = 'orange'
    elif impact_score >= 25:
        impact_level = 'Nhẹ'
        risk_level = 'Thấp'
        alert_level = 'yellow'
    else:
        impact_level = 'Rất nhẹ'
        risk_level = 'Rất thấp'
        alert_level = 'green'

    summary_note = (
        f'Hiện có {total_visible} cá thể trong khung hình, '
        f'{moving_track_count} cá thể đang di chuyển. '
        f'Hướng lan chính: {dominant_direction}.'
    )

    warning_message = None
    warning_signature = 'none'
    if total_visible >= 8:
        warning_message = f'Cảnh báo đỏ: mật độ sâu hại cao ({total_visible} cá thể) trên màn hình live.'
        warning_signature = f'density_{min(total_visible, 12)}'
    elif spread_level == 'Lây lan nhanh' and dominant_direction != 'Không rõ':
        warning_message = f'Cảnh báo: côn trùng đang lây lan nhanh theo hướng {dominant_direction.lower()}.'
        warning_signature = f'spread_fast_{dominant_direction}'
    elif moving_fast_count >= 2 and total_visible >= 4:
        warning_message = (
            f'Cảnh báo: phát hiện {moving_fast_count} cá thể di chuyển nhanh, '
            'nguy cơ lan rộng tăng cao.'
        )
        warning_signature = f'fast_{moving_fast_count}_{total_visible}'

    return {
        'impact_score': impact_score,
        'impact_level': impact_level,
        'risk_level': risk_level,
        'alert_level': alert_level,  # NEW: Mau canh bao (red/orange/yellow/green)
        'avg_speed_px_s': round(avg_speed, 2),
        'moving_count': moving_track_count,
        'total_visible': int(total_visible),
        'dominant_direction': dominant_direction,
        'spread_level': spread_level,
        'summary_note': summary_note,
        'warning_message': warning_message,
        'warning_signature': warning_signature,
    }


@app.route('/health', methods=['GET'])
def health_check():
    return jsonify({
        'success': True,
        'status': 'ok',
        'model_loaded': model is not None,
        'model_type': 'YOLOv11 Medium (Optimized)',
        'inference_modes': {
            'detect': '560px',
            'live_track': '320px',
        },
        'timestamp_ms': int(time.time() * 1000),
    })

@app.route('/detect', methods=['POST'])
def detect_insects():
    if 'image' not in request.files:
        return jsonify({'error': 'Không tìm thấy file ảnh'}), 400
    
    file = request.files['image']
    if file.filename == '':
        return jsonify({'error': 'Chưa chọn file'}), 400

    original_name = file.filename or 'upload.jpg'
    file_ext = os.path.splitext(original_name.lower())[1]
    if file_ext not in ALLOWED_EXTENSIONS:
        return jsonify({'error': 'Định dạng ảnh không hợp lệ. Chỉ hỗ trợ JPG, JPEG, PNG, WEBP.'}), 400

    filename = secure_filename(original_name)
    if filename == '':
        filename = f"upload{file_ext if file_ext else '.jpg'}"

    timestamp = str(int(time.time()))
    save_name = f"{timestamp}_{filename}"
    source_img = decode_uploaded_image(file)
    if source_img is None:
        return jsonify({'error': 'Không thể đọc ảnh tải lên.'}), 400

    prune_folder(RESULT_FOLDER, MAX_STORED_FILES_PER_DIR, MAX_FILE_AGE_SECONDS)

    infer_img = resize_for_inference(source_img, MAX_DETECT_IMAGE_EDGE)

    try:
        results = model(
            infer_img,
            conf=MODEL_CONFIDENCE_THRESHOLD,
            imgsz=DETECT_INFERENCE_IMAGE_SIZE,
            verbose=False,
        )
    except Exception:
        return jsonify({'error': 'Mô hình AI xử lý ảnh thất bại.'}), 500

    detections = []
    pest_counts = {}
    img_base64 = ""
    processed_img = infer_img.copy()
    candidates = []

    if results:
        r = results[0]
        if r.orig_img is not None:
            processed_img = r.orig_img.copy()

        img_h, img_w = processed_img.shape[:2]
        image_area = float(max(img_h * img_w, 1))

        for box in r.boxes:
            class_id = int(box.cls[0])
            if class_id not in ALLOWED_CLASS_IDS:
                continue

            confidence = float(box.conf[0])
            required_conf = get_threshold_for_class(class_id)
            if confidence < required_conf:
                continue

            x1, y1, x2, y2 = box.xyxy[0].tolist()
            x1 = max(0, int(round(x1)))
            y1 = max(0, int(round(y1)))
            x2 = min(img_w, int(round(x2)))
            y2 = min(img_h, int(round(y2)))

            box_w = max(0, x2 - x1)
            box_h = max(0, y2 - y1)
            if box_w == 0 or box_h == 0:
                continue

            area_ratio = (box_w * box_h) / image_area
            if area_ratio < MIN_BOX_AREA_RATIO:
                continue

            class_name = correct_names[class_id]
            candidates.append({
                'class_id': class_id,
                'class_name': class_name,
                'confidence': confidence,
                'area_ratio': area_ratio,
                'bbox': [x1, y1, x2, y2],
            })

    # Ảnh ngoại miền (vd: ảnh người) thường tạo 1 box ocsen mơ hồ.
    # Chỉ loại khi vừa tin cậy thấp vừa box quá nhỏ để tránh bỏ sót ảnh ốc sên rõ.
    if len(candidates) == 1:
        single_item = candidates[0]
        if (
            single_item['class_id'] in STRICT_SINGLE_DETECTION_CLASSES
            and single_item['confidence'] < SINGLE_DETECTION_STRICT_THRESHOLD
            and single_item.get('area_ratio', 0.0) < SINGLE_DETECTION_MIN_AREA_RATIO
        ):
            candidates = []

    detections = [
        {
            'class_name': item['class_name'],
            'confidence': round(float(item['confidence']), 2)
        }
        for item in candidates
    ]

    for item in candidates:
        pest_counts[item['class_name']] = pest_counts.get(item['class_name'], 0) + 1

    plotted_img = draw_detections(processed_img, candidates) if processed_img is not None else None
    result_image_name = ''
    if plotted_img is not None:
        ok, buffer = cv2.imencode('.jpg', plotted_img, [int(cv2.IMWRITE_JPEG_QUALITY), 82])
        if ok:
            img_base64 = base64.b64encode(buffer).decode('utf-8')

        result_image_name = f"result_{save_name}.jpg"
        result_path = os.path.join(RESULT_FOLDER, result_image_name)
        try:
            cv2.imwrite(result_path, plotted_img)
            prune_folder(RESULT_FOLDER, MAX_STORED_FILES_PER_DIR, MAX_FILE_AGE_SECONDS)
        except Exception:
            result_image_name = ''

    return jsonify({
        'success': True,
        'original_image': save_name,
        'result_image': result_image_name,
        'image_base64': img_base64, # Truyền thẳng ảnh dạng Base64 về DataOnline
        'found_pest': len(detections) > 0,
        'message': 'Không tìm thấy sâu bệnh' if len(detections) == 0 else 'Phát hiện sâu bệnh',
        'total_insects': len(detections),
        'pest_counts': pest_counts,
        'detections': detections
    })


@app.route('/live_track', methods=['POST'])
def live_track():
    payload = request.get_json(silent=True)
    if not isinstance(payload, dict):
        return jsonify({'success': False, 'error': 'Dữ liệu gửi lên không hợp lệ.'}), 400

    frame_data = payload.get('frame_data', '')
    frame = decode_frame_data(frame_data)
    if frame is None:
        return jsonify({'success': False, 'error': 'Không đọc được khung hình camera.'}), 400

    frame = resize_for_inference(frame, MAX_LIVE_FRAME_EDGE)
    if frame is None:
        return jsonify({'success': False, 'error': 'Khung hình camera không hợp lệ.'}), 400

    session_id = str(payload.get('session_id', '')).strip()
    if session_id == '':
        session_id = f"live_{uuid.uuid4().hex[:12]}"
    session_id = ''.join(ch for ch in session_id if ch.isalnum() or ch in {'_', '-'})[:64] or f"live_{uuid.uuid4().hex[:12]}"

    timestamp_raw = payload.get('timestamp_ms')
    try:
        timestamp_seconds = float(timestamp_raw) / 1000.0
    except (TypeError, ValueError):
        timestamp_seconds = time.time()
    if timestamp_seconds <= 0:
        timestamp_seconds = time.time()

    prune_live_sessions()
    session = get_live_session(session_id)
    tracks = session['tracks']

    try:
        infer_results = model(
            frame,
            conf=LIVE_CONFIDENCE_THRESHOLD,
            imgsz=LIVE_INFERENCE_IMAGE_SIZE,
            verbose=False,
        )
    except Exception as e:
        print(f"[LIVE TRACK ERROR] Model inference failed: {e}")
        return jsonify({'success': False, 'error': 'Mô hình AI xử lý video thất bại.'}), 500

    detections = []
    species_counts = {}
    
    # DEBUG: Kiểm tra frame size và detection
    frame_h_orig = frame.shape[0] if frame is not None else 0
    frame_w_orig = frame.shape[1] if frame is not None else 0
    raw_detection_count = 0
    filtered_count = 0

    if infer_results:
        result = infer_results[0]
        img_h, img_w = result.orig_shape
        image_area = float(max(img_h * img_w, 1))
        raw_detection_count = len(result.boxes)
        
        # DEBUG LOG
        if raw_detection_count > 0:
            print(f"[LIVE] Frame: {frame_w_orig}x{frame_h_orig} → Inferred as {img_w}x{img_h}, Raw detections: {raw_detection_count}")

        for box in result.boxes:
            class_id = int(box.cls[0])
            if class_id not in ALLOWED_CLASS_IDS:
                continue

            confidence = float(box.conf[0])
            required_conf = get_live_threshold_for_class(class_id)  # Dùng threshold của live
            if confidence < required_conf:
                filtered_count += 1
                if raw_detection_count > 0 and raw_detection_count <= 5:
                    print(f"  ✗ Box filtered (low conf): conf={confidence:.3f} < {required_conf} (class={CLASS_NAMES[class_id]})")
                continue

            x1, y1, x2, y2 = box.xyxy[0].tolist()
            x1 = max(0, int(round(x1)))
            y1 = max(0, int(round(y1)))
            x2 = min(img_w, int(round(x2)))
            y2 = min(img_h, int(round(y2)))

            box_w = max(0, x2 - x1)
            box_h = max(0, y2 - y1)
            if box_w == 0 or box_h == 0:
                filtered_count += 1
                continue

            area_ratio = (box_w * box_h) / image_area
            if area_ratio < MIN_BOX_AREA_RATIO:
                filtered_count += 1
                if raw_detection_count > 0 and raw_detection_count <= 5:
                    print(f"  ✗ Box filtered (small area): {box_w}x{box_h} → ratio={area_ratio:.6f} < {MIN_BOX_AREA_RATIO}")
                continue

            class_name = correct_names[class_id]
            cx = (x1 + x2) / 2.0
            cy = (y1 + y2) / 2.0

            detections.append({
                'class_id': class_id,
                'class_name': class_name,
                'confidence': confidence,
                'bbox': [x1, y1, x2, y2],
                'cx': cx,
                'cy': cy,
            })
            species_counts[class_name] = species_counts.get(class_name, 0) + 1
    
    # DEBUG: Final summary
    if raw_detection_count > 0:
        print(f"[LIVE] Final: Raw={raw_detection_count}, Filtered={filtered_count}, Valid detections={len(detections)}")

    frame_h, frame_w = frame.shape[:2]
    assignments = match_detections_to_tracks(detections, tracks, frame_w, frame_h, timestamp_seconds)

    notifications = []
    visible_track_ids = set()

    for det_index, det in enumerate(detections):
        track_id = assignments.get(det_index)

        if track_id is None:
            track_id = int(session['next_track_id'])
            session['next_track_id'] = track_id + 1

            tracks[track_id] = {
                'id': track_id,
                'class_id': det['class_id'],
                'class_name': det['class_name'],
                'first_seen_at': timestamp_seconds,
                'last_seen_at': timestamp_seconds,
                'first_centroid': [det['cx'], det['cy']],
                'last_centroid': [det['cx'], det['cy']],
                'last_bbox': det['bbox'],
                'avg_confidence': det['confidence'],
                'observation_count': 1,
                'total_distance_px': 0.0,
                'last_speed_px_s': 0.0,
                'max_speed_px_s': 0.0,
                'net_dx': 0.0,
                'net_dy': 0.0,
                'current_direction': 'Đứng yên',
                'direction_changed_at': timestamp_seconds,
                'direction_events': [],
            }
            notifications.append(f"Phát hiện mới: {get_class_name_vi(det['class_name'])} #{track_id} trong vùng quan sát.")
            speed_now = 0.0
        else:
            track = tracks[track_id]
            dt = max(timestamp_seconds - float(track['last_seen_at']), 0.001)

            prev_x, prev_y = track['last_centroid']
            distance_px = math.hypot(det['cx'] - prev_x, det['cy'] - prev_y)
            speed_now = distance_px / dt

            track['last_seen_at'] = timestamp_seconds
            track['last_centroid'] = [det['cx'], det['cy']]
            track['last_bbox'] = det['bbox']
            track['total_distance_px'] = float(track['total_distance_px']) + distance_px
            track['last_speed_px_s'] = speed_now
            track['max_speed_px_s'] = max(float(track['max_speed_px_s']), speed_now)
            track['observation_count'] = int(track['observation_count']) + 1
            track['avg_confidence'] = (
                (float(track['avg_confidence']) * (track['observation_count'] - 1) + det['confidence']) /
                max(track['observation_count'], 1)
            )

            first_x, first_y = track['first_centroid']
            net_dx = float(det['cx']) - float(first_x)
            net_dy = float(det['cy']) - float(first_y)
            track['net_dx'] = net_dx
            track['net_dy'] = net_dy

            previous_direction = str(track.get('current_direction', 'Đứng yên'))
            new_direction = vector_direction_label(net_dx, net_dy)

            if new_direction != previous_direction:
                direction_started_at = float(track.get('direction_changed_at') or track.get('first_seen_at') or timestamp_seconds)
                travel_time = max(timestamp_seconds - direction_started_at, 0.0)

                if new_direction != 'Đứng yên':
                    direction_event = {
                        'track_id': track_id,
                        'class_name': det['class_name'],
                        'class_name_vi': get_class_name_vi(det['class_name']),
                        'from_direction': previous_direction,
                        'to_direction': new_direction,
                        'duration_seconds': round(travel_time, 2),
                        'timestamp': int(timestamp_seconds * 1000),
                    }
                    track['direction_events'].append(direction_event)
                    if len(track['direction_events']) > LIVE_MAX_DIRECTION_EVENTS_PER_TRACK:
                        track['direction_events'] = track['direction_events'][-LIVE_MAX_DIRECTION_EVENTS_PER_TRACK:]

                    if previous_direction == 'Đứng yên':
                        notifications.append(
                            f"{get_class_name_vi(det['class_name'])} #{track_id} bắt đầu di chuyển hướng {new_direction.lower()}."
                        )
                    else:
                        notifications.append(
                            f"{get_class_name_vi(det['class_name'])} #{track_id} đổi hướng từ {previous_direction.lower()} sang {new_direction.lower()}."
                        )

                track['direction_changed_at'] = timestamp_seconds
                track['current_direction'] = new_direction

        track_ref = tracks[track_id]
        direction_event_count = len(track_ref.get('direction_events', []))
        movement_level = movement_level_from_speed(speed_now, direction_event_count)

        det['track_id'] = track_id
        det['speed_px_s'] = round(speed_now, 2)
        det['direction'] = track_ref.get('current_direction') or 'Đứng yên'
        det['movement_level'] = movement_level
        visible_track_ids.add(int(track_id))

    stale_track_ids = [
        track_id for track_id, track in tracks.items()
        if (timestamp_seconds - float(track['last_seen_at'])) > LIVE_TRACK_STALE_SECONDS
    ]
    for track_id in stale_track_ids:
        tracks.pop(track_id, None)

    all_recent_spread_events = []
    active_track_rows = []

    for track_id, track in tracks.items():
        if (timestamp_seconds - float(track['last_seen_at'])) > LIVE_TRACK_STALE_SECONDS:
            continue
        if track_id not in visible_track_ids:
            continue

        track_events = track.get('direction_events', [])
        for event in track_events:
            all_recent_spread_events.append(event)

        move_level = movement_level_from_speed(float(track.get('last_speed_px_s', 0.0)), len(track_events))
        observed_seconds = max(timestamp_seconds - float(track['first_seen_at']), 0.0)
        net_dx = float(track.get('net_dx', 0.0))
        net_dy = float(track.get('net_dy', 0.0))
        displacement = math.hypot(net_dx, net_dy)

        active_track_rows.append({
            'track_id': track_id,
            'class_name': track['class_name'],
            'class_name_vi': get_class_name_vi(track['class_name']),
            'confidence': round(float(track.get('avg_confidence', 0.0)), 2),
            'direction': track.get('current_direction') or 'Đứng yên',
            'observed_seconds': round(observed_seconds, 1),
            'distance_px': round(float(track.get('total_distance_px', 0.0)), 1),
            'displacement_px': round(displacement, 1),
            'speed_px_s': round(float(track.get('last_speed_px_s', 0.0)), 2),
            'max_speed_px_s': round(float(track.get('max_speed_px_s', 0.0)), 2),
            'movement_level': move_level,
        })

    all_recent_spread_events.sort(key=lambda event: event.get('timestamp', 0), reverse=True)
    all_recent_spread_events = all_recent_spread_events[:LIVE_MAX_RESPONSE_TRACKS]

    active_track_rows.sort(key=lambda row: row['speed_px_s'], reverse=True)
    active_track_rows = active_track_rows[:LIVE_MAX_RESPONSE_TRACKS]

    visible_tracks = [tracks[track_id] for track_id in visible_track_ids if track_id in tracks]
    impact_summary = compute_impact_summary(species_counts, visible_tracks, all_recent_spread_events, len(detections))

    warning_message = str(impact_summary.get('warning_message') or '').strip()
    warning_signature = str(impact_summary.get('warning_signature') or 'none')
    last_warning_signature = str(session.get('last_warning_signature', ''))
    last_warning_at = float(session.get('last_warning_at', 0.0))

    if warning_message:
        if warning_signature != last_warning_signature or (timestamp_seconds - last_warning_at) > LIVE_WARNING_COOLDOWN_SECONDS:
            notifications.append(warning_message)
            session['last_warning_signature'] = warning_signature
            session['last_warning_at'] = timestamp_seconds
    elif (timestamp_seconds - last_warning_at) > LIVE_WARNING_COOLDOWN_SECONDS:
        session['last_warning_signature'] = ''

    detection_rows = []
    for det in detections:
        detection_rows.append({
            'track_id': det['track_id'],
            'class_name': det['class_name'],
            'class_name_vi': get_class_name_vi(det['class_name']),
            'confidence': round(det['confidence'], 2),
            'bbox': det['bbox'],
            'direction': det['direction'],
            'movement_level': det['movement_level'],
            'speed_px_s': det['speed_px_s'],
        })

    # Tao heat map data cho visualization
    centroid_heatmap = []
    for det in detections:
        track_id = det.get('track_id')
        track_data = tracks.get(track_id, {})
        speed = det.get('speed_px_s', 0.0)
        
        centroid_heatmap.append({
            'x': int(det['cx']),
            'y': int(det['cy']),
            'class': det['class_name'],
            'class_vi': get_class_name_vi(det['class_name']),
            'speed': speed,
            'intensity': min(100, int(speed * 2 + 20)),  # 20-100 intensity
        })
    
    return jsonify({
        'success': True,
        'session_id': session_id,
        'timestamp_ms': int(timestamp_seconds * 1000),
        'frame_size': {'width': frame_w, 'height': frame_h},
        'total_detected': len(detections),
        'active_pest_count': len(detections),
        'species_counts': species_counts,
        'detections': detection_rows,
        'tracks': active_track_rows,
        'spread_events': all_recent_spread_events,
        'movement_summary': impact_summary,
        'centroid_heatmap': centroid_heatmap,
        'notifications': notifications,
    })

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=7860)
