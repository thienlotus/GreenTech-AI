# Export data structure cho CSV/JSON
# Thêm vào api_model.py - phần export_live_report()

import csv
import json
from datetime import datetime

def export_live_report_csv(session_id, movement_summary, detections, tracks):
    """Export live tracking data to CSV format"""
    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
    filename = f"live_report_{session_id}_{timestamp}.csv"
    
    # CSV header and data
    report_data = {
        'timestamp': datetime.now().isoformat(),
        'session_id': session_id,
        'impact_level': movement_summary.get('impact_level', 'Unknown'),
        'alert_level': movement_summary.get('alert_level', 'green'),
        'total_detected': len(detections),
        'total_visible': movement_summary.get('total_visible', 0),
        'moving_count': movement_summary.get('moving_count', 0),
        'avg_speed': movement_summary.get('avg_speed_px_s', 0),
        'dominant_direction': movement_summary.get('dominant_direction', 'Unknown'),
        'spread_level': movement_summary.get('spread_level', 'Unknown'),
    }
    
    return report_data, filename


def export_tracking_history_json(session_data):
    """Export complete tracking history"""
    history = {
        'session_id': session_data.get('session_id', 'unknown'),
        'start_time': datetime.now().isoformat(),
        'tracks': [],
        'events': [],
    }
    
    # Add track info
    for track_id, track in session_data.get('tracks', {}).items():
        history['tracks'].append({
            'track_id': int(track_id),
            'species': track.get('class_name', 'unknown'),
            'species_vi': track.get('class_name_vi', 'Unknown'),
            'total_distance': track.get('total_distance_px', 0),
            'max_speed': track.get('max_speed_px_s', 0),
            'observation_count': track.get('observation_count', 0),
            'direction_events': track.get('direction_events', [])[:5],  # Last 5 events
        })
    
    return history
