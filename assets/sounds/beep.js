// Generate a beep sound using Web Audio API
function generateBeepSound() {
    // Check if AudioContext is supported
    if (typeof AudioContext !== 'undefined' || typeof webkitAudioContext !== 'undefined') {
        // Create audio context
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        const audioContext = new AudioContextClass();
        
        // Create oscillator
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        // Configure oscillator
        oscillator.type = 'sine';
        oscillator.frequency.value = 1000; // Frequency in Hz
        
        // Configure gain (volume)
        gainNode.gain.value = 0.5;
        
        // Connect nodes
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        // Start and stop the sound
        const now = audioContext.currentTime;
        oscillator.start(now);
        oscillator.stop(now + 0.2); // 200ms duration
        
        // Export the sound to WAV format
        setTimeout(() => {
            audioContext.close();
        }, 300);
    }
}

// Call this function to generate a beep sound
// generateBeepSound();

// Create a data URI for a beep sound (WAV format)
const beepSoundBase64 = 'UklGRjQnAABXQVZFZm10IBAAAAABAAEARKwAAESsAAABAAgAZGF0YRAnAAAAAAEBAQICAwMEBAUFBgYHBwgICQkKCgsLDAwNDQ4ODw8QEBERERITExQUFRUWFhcXGBgZGRoaGxscHB0dHh4fHyAgISEiIiMjJCQlJSYmJycoKCkpKiorKywsLS0uLi8vMDAxMTIyMzM0NDU1NjY3Nzg4OTk6Ojs7PDw9PT4+Pz9AQEFBQkJDQ0RERUVGRkdHSEhJSUpKS0tMTE1NTk5PT1BQUVFSUlNTVFRVVVZWV1dYWFhZWlpbW1xcXV1eXl9fYGBhYWJiY2NkZGVlZmZnZ2hoaWlqamtrbGxtbW5ub29wcHFxcnJzc3R0dXV2dnd3eHh5eXp6e3t8fH19fn5/f4CAgYGCgoODhISFhYaGh4eIiImJioqLi4yMjY2Ojo+PkJCRkZKSk5OUlJWVlpaXl5iYmZmampubnJydnZ6en5+goKGhoqKjo6SkpaWmpqenqKipqaqqq6usrK2trq6vr7CwsbGysrOztLS1tba2t7e4uLm5urq7u7y8vb2+vr+/wMDBwcLCw8PExMXFxsbHx8jIycnKysvLzMzNzc7Oz8/Q0NHR0tLT09TU1dXW1tfX2NjZ2dra29vc3N3d3t7f3+Dg4eHi4uPj5OTl5ebm5+fo6Onp6urr6+zs7e3u7u/v8PDx8fLy8/P09PX19vb39/j4+fn6+vv7/Pz9/f7+//8AAAABAQECA...';

// Function to create WAV file from base64 data
function createBeepSoundFile() {
    // Convert base64 to blob
    const byteCharacters = atob(beepSoundBase64);
    const byteNumbers = new Array(byteCharacters.length);
    for (let i = 0; i < byteCharacters.length; i++) {
        byteNumbers[i] = byteCharacters.charCodeAt(i);
    }
    const byteArray = new Uint8Array(byteNumbers);
    const blob = new Blob([byteArray], { type: 'audio/wav' });
    
    // Create URL for the blob
    const blobUrl = URL.createObjectURL(blob);
    
    // Create audio element
    const audio = new Audio(blobUrl);
    
    return audio;
}

// Export the beep sound function
window.playBeepSound = function() {
    generateBeepSound();
}; 